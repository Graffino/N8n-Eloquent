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
	event: string;
	verifyHmac: boolean;
	requireTimestamp: boolean;
	expectedSourceIp: string;
}

interface IMetadata extends IDataObject {
	source_trigger?: {
		node_id: string;
		workflow_id: string | undefined;
		event: string;
		timestamp: string;
	} | undefined;
	workflow_id?: string;
	node_id?: string;
	execution_id?: string;
	is_n8n_event_listener?: boolean;
}

export class LaravelEventListener implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Laravel Event Listener',
		name: 'laravelEventListener',
		icon: 'file:laravel.svg',
		group: ['trigger'],
		version: 1,
		subtitle: '={{$parameter["event"]}}',
		description: 'Triggers when Laravel events are dispatched',
		defaults: {
			name: 'Laravel Event Listener',
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
				displayName: 'Event',
				name: 'event',
				type: 'options',
				typeOptions: {
					loadOptionsMethod: 'getEvents',
				},
				default: '',
				required: true,
				description: 'The Laravel event class to listen for',
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
			async getEvents(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					
					const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'GET',
						url: `${baseUrl}/api/n8n/events`,
						json: true,
						skipSslCertificateValidation: true,
					});

					const events = response.events.map((event: any) => ({
						name: event.name,
						value: event.class,
						description: `Full class: ${event.class}`,
					}));

					return events;
				} catch (error) {
					console.error('❌ Failed to load events:', error);

					throw new NodeOperationError(this.getNode(), `Failed to load events: ${(error as Error).message}`);
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
					const event = this.getNodeParameter('event') as string;
					const verifyHmac = this.getNodeParameter('verifyHmac', false) as boolean;
					const requireTimestamp = this.getNodeParameter('requireTimestamp', false) as boolean;
					const expectedSourceIp = this.getNodeParameter('expectedSourceIp', '') as string;
					
					// Store node parameters in workflow static data for webhook execution
					const webhookData = this.getWorkflowStaticData('node');
					webhookData.nodeParameters = {
						event,
						verifyHmac,
						requireTimestamp,
						expectedSourceIp,
					};
					
					const webhookUrl = this.getNodeWebhookUrl('default');
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					
					const requestBody = {
						event,
						webhook_url: webhookUrl,
						node_id: this.getNode().id,
						workflow_id: this.getWorkflow().id,
						verify_hmac: verifyHmac,
						require_timestamp: requireTimestamp,
						expected_source_ip: expectedSourceIp || null,
					};
					
					const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'POST',
						url: `${baseUrl}/api/n8n/events/subscribe`,
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
					console.error('❌ Failed to register event webhook:', error);
					
					throw new NodeOperationError(this.getNode(), `Failed to register event webhook: ${(error as Error).message}`);
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
						url: `${baseUrl}/api/n8n/events/unsubscribe`,
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
					console.error('❌ Failed to unregister event webhook:', error);
					
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
			const eventClass = body.event_class as string;
			const data = body.data as IDataObject;
			let metadata = body.metadata as IMetadata;
			
			// Add trigger node information to metadata for loop detection
			if (!metadata) {
				metadata = {};
			}
			
			metadata.source_trigger = {
				node_id: this.getNode().id,
				workflow_id: this.getWorkflow().id,
				event: eventClass,
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
								event_class: eventClass,
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
			console.error('❌ Error processing event webhook:', error);
			
			// Return error response
			return {
				webhookResponse: {
					statusCode: 400,
					body: { 
						error: 'Event webhook processing failed',
						message: (error as Error).message 
					},
				},
			};
		}
	}
} 