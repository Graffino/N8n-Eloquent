import {
	INodeType,
	INodeTypeDescription,
	IWebhookFunctions,
	IWebhookResponseData,
	NodeConnectionType,
} from 'n8n-workflow';

import {
	IDataObject,
	INodePropertyOptions,
	NodeOperationError,
	IHookFunctions,
	ILoadOptionsFunctions,
} from 'n8n-workflow';

import { createHmac, timingSafeEqual } from 'crypto';

interface INodeParameters {
	model: string;
	events: string[];
	verifyHmac: boolean;
	requireTimestamp: boolean;
	expectedSourceIp: string;
}

interface IMetadata extends IDataObject {
	source_trigger?: {
		node_id: string;
		workflow_id: string | undefined;
		model: string;
		event: string;
		timestamp: string;
	} | undefined;
	workflow_id?: string;
	node_id?: string;
	execution_id?: string;
	is_n8n_crud?: boolean;
}

export class LaravelEloquentTrigger implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Laravel Eloquent Trigger',
		name: 'laravelEloquentTrigger',
		icon: 'file:laravel.svg',
		group: ['trigger'],
		version: 1,
		subtitle: '={{$parameter["model"] + " - " + $parameter["events"].join(", ")}}',
		description: 'Triggers when Laravel Eloquent model events occur',
		defaults: {
			name: 'Laravel Eloquent Trigger',
		},
		inputs: [],
		outputs: [NodeConnectionType.Main],
		credentials: [
			{
				name: 'laravelEloquentApi',
				required: true,
			},
		],
		webhooks: [
			{
				name: 'default',
				httpMethod: 'POST',
				responseMode: 'onReceived',
				path: 'webhook',
			},
		],
		properties: [
			{
				displayName: 'Model',
				name: 'model',
				type: 'options',
				typeOptions: {
					loadOptionsMethod: 'getModels',
				},
				default: '',
				required: true,
				description: 'The Laravel Eloquent model class to monitor',
			},
			{
				displayName: 'Events',
				name: 'events',
				type: 'multiOptions',
				options: [
					{
						name: 'Created',
						value: 'created',
						description: 'Triggered when a new model is created',
					},
					{
						name: 'Updated',
						value: 'updated',
						description: 'Triggered when a model is updated',
					},
					{
						name: 'Deleted',
						value: 'deleted',
						description: 'Triggered when a model is deleted',
					},
					{
						name: 'Restored',
						value: 'restored',
						description: 'Triggered when a soft-deleted model is restored',
					},
					{
						name: 'Saving',
						value: 'saving',
						description: 'Triggered before a model is saved (created or updated)',
					},
					{
						name: 'Saved',
						value: 'saved',
						description: 'Triggered after a model is saved (created or updated)',
					},
				],
				default: ['created', 'updated', 'deleted'],
				required: true,
				description: 'The model events to listen for',
			},
			{
				displayName: 'Verify HMAC Signature',
				name: 'verifyHmac',
				type: 'boolean',
				default: true,
				description: 'Whether to verify the HMAC signature of incoming webhooks for security',
			},
			{
				displayName: 'Require Timestamp Validation',
				name: 'requireTimestamp',
				type: 'boolean',
				default: true,
				displayOptions: {
					show: {
						verifyHmac: [true],
					},
				},
				description: 'Reject webhooks older than 5 minutes to prevent replay attacks',
			},
			{
				displayName: 'Expected Source IP',
				name: 'expectedSourceIp',
				type: 'string',
				default: '',
				placeholder: '192.168.1.100 or 192.168.1.0/24',
				description: 'Optional: Restrict webhooks to specific IP address or CIDR range',
			},
		],
	};

	methods = {
		loadOptions: {
			async getModels(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					
					const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'GET',
						url: `${baseUrl}/api/n8n/models`,
						json: true,
						skipSslCertificateValidation: true,
					});

					const models = response.models.map((model: any) => ({
						name: model.name.split('\\').pop(),
						value: model.class,
						description: `Full class: ${model.class}`,
					}));
					
					return models;
				} catch (error) {
					console.error('❌ Failed to load models:', error);
					
					throw new NodeOperationError(this.getNode(), `Failed to load models: ${(error as Error).message}`);
				}
			},
		},
	};

	// Webhook lifecycle methods - these are the correct method names that n8n calls
	webhookMethods = {
		default: {
			async checkExists(this: IHookFunctions): Promise<boolean> {
				// This method is called when the workflow is activated
				// Return false to indicate we don't have existing data to process
				return false;
			},

			async create(this: IHookFunctions): Promise<boolean> {
				// This method is called when the workflow is activated or saved
				// It should register the webhook with the Laravel application
				
				try {
					const model = this.getNodeParameter('model') as string;
					const events = this.getNodeParameter('events') as string[];
					const verifyHmac = this.getNodeParameter('verifyHmac', false) as boolean;
					const requireTimestamp = this.getNodeParameter('requireTimestamp', false) as boolean;
					const expectedSourceIp = this.getNodeParameter('expectedSourceIp', '') as string;
					
					// Store node parameters in workflow static data for webhook execution
					const webhookData = this.getWorkflowStaticData('node');
					webhookData.nodeParameters = {
						model,
						events,
						verifyHmac,
						requireTimestamp,
						expectedSourceIp,
					};
					
					const webhookUrl = this.getNodeWebhookUrl('default');
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					
					const requestBody = {
						model,
						events,
						webhook_url: webhookUrl,
						node_id: this.getNode().id,
						workflow_id: this.getWorkflow().id,
						verify_hmac: verifyHmac,
						require_timestamp: requireTimestamp,
						expected_source_ip: expectedSourceIp || null,
					};
					
					const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'POST',
						url: `${baseUrl}/api/n8n/webhooks/subscribe`,
						body: requestBody,
						json: true,
						skipSslCertificateValidation: true,
					});
					
					// Store the subscription ID for later deletion
					if (response.subscription && response.subscription.id) {
						const webhookData = this.getWorkflowStaticData('node');
						webhookData.subscriptionId = response.subscription.id;
					}
					
					return true;
				} catch (error) {
					console.error('❌ Failed to register webhook:', error);
					
					throw new NodeOperationError(this.getNode(), `Failed to register webhook: ${(error as Error).message}`);
				}
			},

			async delete(this: IHookFunctions): Promise<boolean> {
				// This method is called when the workflow is deactivated or the node is deleted
				// It should unregister the webhook from the Laravel application
				
				const webhookData = this.getWorkflowStaticData('node');
				
				// Check if we have a subscription ID to delete
				if (!webhookData.subscriptionId) {
					return true;
				}

				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					
					await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'DELETE',
						url: `${baseUrl}/api/n8n/webhooks/unsubscribe`,
						body: {
							subscription_id: webhookData.subscriptionId,
						},
						json: true,
						skipSslCertificateValidation: true,
					});
					
					// Clear the subscription ID
					delete webhookData.subscriptionId;
					
					return true;
				} catch (error) {
					console.error('❌ Failed to unregister webhook:', error);
					
					// Don't throw error here as it might prevent workflow deactivation
					return true;
				}
			},
		},
	};

	async webhook(this: IWebhookFunctions): Promise<IWebhookResponseData> {
		try {
			const body = this.getBodyData() as IDataObject;
			const headers = this.getHeaderData() as IDataObject;
			
			// Get the raw request body for HMAC verification
			const rawBody = typeof this.getRequestObject().body === 'string' 
				? this.getRequestObject().body 
				: JSON.stringify(this.getRequestObject().body);
			
			// Get stored node parameters
			const webhookData = this.getWorkflowStaticData('node');
			const nodeParameters = webhookData.nodeParameters as INodeParameters;
			
			if (!nodeParameters) {
				throw new NodeOperationError(this.getNode(), 'No node parameters found for webhook processing');
			}
			
			// Validate HMAC signature if enabled
			if (nodeParameters.verifyHmac) {
				const signature = headers['x-n8n-signature'] as string;
				const credentials = await this.getCredentials('laravelEloquentApi');
				const hmacSecret = credentials.hmacSecret as string;
				
				if (!signature || !hmacSecret) {
					throw new NodeOperationError(this.getNode(), 'Missing HMAC signature or HMAC secret for verification');
				}
				
				// Calculate expected signature using the raw body (same as Laravel)
				const expectedSignature = createHmac('sha256', hmacSecret)
					.update(rawBody)
					.digest('hex');
				
				// Use timing-safe comparison
				if (!timingSafeEqual(Buffer.from(signature), Buffer.from(expectedSignature))) {
					throw new NodeOperationError(this.getNode(), 'HMAC signature verification failed');
				}
			}
			
			// Validate timestamp if required
			if (nodeParameters.requireTimestamp) {
				const timestamp = body.timestamp as string;
				if (!timestamp) {
					throw new NodeOperationError(this.getNode(), 'Missing timestamp in webhook payload');
				}
				
				const webhookTime = new Date(timestamp).getTime();
				const currentTime = Date.now();
				const timeDiff = Math.abs(currentTime - webhookTime);
				const maxAge = 5 * 60 * 1000; // 5 minutes
				
				if (timeDiff > maxAge) {
					throw new NodeOperationError(this.getNode(), 'Webhook timestamp too old (replay attack protection)');
				}
			}
			
			// Validate source IP if specified
			if (nodeParameters.expectedSourceIp) {
				const sourceIp = headers['x-forwarded-for'] as string || 
								headers['x-real-ip'] as string || 
								headers['cf-connecting-ip'] as string ||
								headers['x-client-ip'] as string;
				
				if (!sourceIp) {
					throw new NodeOperationError(this.getNode(), 'Could not determine source IP for validation');
				}
				
				// Simple IP validation (you might want to use a proper CIDR library)
				let isIpAllowed = false;
				if (!nodeParameters.expectedSourceIp.includes('/')) {
					// Single IP comparison
					isIpAllowed = sourceIp === nodeParameters.expectedSourceIp;
				} else {
					// For CIDR ranges, this is a simplified check
					// In production, you should use a proper CIDR library
					const [rangeIp, bits] = nodeParameters.expectedSourceIp.split('/');
					const mask = parseInt(bits);
					
					// Convert IPs to integers for comparison
					const ipToNumber = (ip: string) => ip.split('.').reduce((acc, octet) => (acc << 8) + parseInt(octet), 0) >>> 0;
					const ipNum = ipToNumber(sourceIp);
					const rangeNum = ipToNumber(rangeIp);
					const maskNum = (0xFFFFFFFF << (32 - mask)) >>> 0;
					
					isIpAllowed = (ipNum & maskNum) === (rangeNum & maskNum);
				}
				
				if (!isIpAllowed) {
					throw new NodeOperationError(this.getNode(), `Source IP ${sourceIp} not in allowed range ${nodeParameters.expectedSourceIp}`);
				}
			}
			
			// Extract the relevant data from the webhook payload
			const event = body.event as string;
			const model = body.model as string;
			const data = body.data as IDataObject;
			let metadata = body.metadata as IMetadata;
			
			// Add trigger node information to metadata for loop detection
			if (!metadata) {
				metadata = {};
			}
			
			metadata.source_trigger = {
				node_id: this.getNode().id,
				workflow_id: this.getWorkflow().id,
				model: nodeParameters.model,
				event: event,
				timestamp: new Date().toISOString()
			};

			// Return the data in the format n8n expects
			return {
				webhookResponse: {
					statusCode: 200,
					body: { success: true },
				},
				workflowData: [
					[
						{
							json: {
								event,
								model,
								data,
								metadata,
								timestamp: body.timestamp,
								subscription_id: headers['x-n8n-subscription-id'],
							},
						},
					],
				],
			};
		} catch (error) {
			console.error('❌ Error processing webhook:', error);

			// Return error response
			return {
				webhookResponse: {
					statusCode: 400,
					body: { 
						error: 'Webhook processing failed',
						message: (error as Error).message 
					},
				},
			};
		}
	}
} 