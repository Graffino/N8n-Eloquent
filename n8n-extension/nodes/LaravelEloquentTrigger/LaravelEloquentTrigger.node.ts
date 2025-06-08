import {
	IWebhookFunctions,
	IDataObject,
	INodeType,
	INodeTypeDescription,
	IWebhookResponseData,
	NodeConnectionType,
	NodeOperationError,
	IHookFunctions,
	ILoadOptionsFunctions,
	INodePropertyOptions,
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
		workflow_id: string;
		model: string;
		event: string;
		timestamp: string;
	} | undefined;
}

interface IItemMetadata {
	metadata?: {
		source_trigger?: IMetadata['source_trigger'];
	};
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
				console.log('📋 getModels() called - Loading model options');
				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					
					console.log('🔑 Using credentials with baseUrl:', baseUrl);
					console.log('🌐 Making request to /api/n8n/models');
					
					const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'GET',
						url: `${baseUrl}/api/n8n/models`,
						json: true,
					});

					console.log('✅ Models response:', response);

					const models = response.models.map((model: any) => ({
						name: model.name.split('\\').pop(),
						value: model.class,
						description: `Full class: ${model.class}`,
					}));
					
					console.log('📋 Returning models:', models);
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
				console.log('🔍 webhookMethods.checkExists() called');
				console.log('🔍 Node ID:', this.getNode().id);
				console.log('🔍 Workflow ID:', this.getWorkflow().id);
				return false;
			},

			async create(this: IHookFunctions): Promise<boolean> {
				// This method is called when the workflow is activated or saved
				// It should register the webhook with the Laravel application
				
				console.log('🚀 webhookMethods.create() called - WEBHOOK REGISTRATION STARTING');
				console.log('🔍 Node context:', {
					nodeId: this.getNode().id,
					nodeName: this.getNode().name,
					workflowId: this.getWorkflow().id,
				});
				
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
					
					console.log('📋 Webhook registration details:', {
						model,
						events,
						webhookUrl,
						verifyHmac,
						requireTimestamp,
						expectedSourceIp: expectedSourceIp || 'none',
					});
					
					console.log('🌐 Making authenticated request to webhook subscription endpoint');
					
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
					
					console.log('📦 Request body:', requestBody);
					
					const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'POST',
						url: `${baseUrl}/api/n8n/webhooks/subscribe`,
						body: requestBody,
						json: true,
					});
					
					console.log('✅ Webhook registration successful:', response);
					return true;
				} catch (error) {
					console.error('❌ Webhook registration failed:', error);
					throw new NodeOperationError(this.getNode(), `Failed to register webhook: ${(error as Error).message}`);
				}
			},

			async delete(this: IHookFunctions): Promise<boolean> {
				// This method is called when the workflow is deactivated or the node is deleted
				// It should unregister the webhook from the Laravel application
				
				console.log('🗑️ webhookMethods.delete() called - WEBHOOK UNREGISTRATION STARTING');
				console.log('🔍 Node ID:', this.getNode().id);
				console.log('🔍 Workflow ID:', this.getWorkflow().id);
				
				const webhookData = this.getWorkflowStaticData('node');
				
				// Check if we have a subscription ID to delete
				if (!webhookData.subscriptionId) {
					console.log('⚠️ No subscription ID found, skipping webhook deletion');
					return true;
				}

				console.log('🔍 Found subscription ID to delete:', webhookData.subscriptionId);

				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					
					console.log('🌐 Making authenticated request to webhook unsubscription endpoint');
					console.log('📦 Request body:', { subscription_id: webhookData.subscriptionId });
					
					await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'DELETE',
						url: `${baseUrl}/api/n8n/webhooks/unsubscribe`,
						body: {
							subscription_id: webhookData.subscriptionId,
						},
						json: true,
					});
					
					// Clear the subscription ID
					delete webhookData.subscriptionId;
					
					console.log('✅ Laravel Eloquent webhook unregistered successfully');
					return true;
				} catch (error) {
					console.error('❌ Failed to unregister Laravel Eloquent webhook:', error);
					// Don't throw error here as it might prevent workflow deactivation
					return true;
				}
			},
		},
	};

	async webhook(this: IWebhookFunctions): Promise<IWebhookResponseData> {
		console.log('📨 webhook() called - Received webhook data');
		
		const bodyData = this.getBodyData() as IDataObject;
		const headers = this.getHeaderData() as IDataObject;
		const req = this.getRequestObject();
		const credentials = await this.getCredentials('laravelEloquentApi');

		console.log('📨 Webhook body data:', bodyData);
		console.log('📨 Webhook headers:', headers);

		// Get node parameters from workflow static data
		const webhookData = this.getWorkflowStaticData('node');
		console.log('📨 Webhook static data:', webhookData);
		
		const nodeParameters = (webhookData.nodeParameters || {}) as INodeParameters;
		console.log('📨 Node parameters:', nodeParameters);

		// Add trigger node information to metadata
		if (!bodyData.metadata) {
			bodyData.metadata = {};
		}

		(bodyData.metadata as IDataObject).source_trigger = {
			node_id: this.getNode().id,
			workflow_id: this.getWorkflow().id,
			model: nodeParameters.model,
			event: bodyData.event as string,
			timestamp: new Date().toISOString()
		};

		// Helper functions for security validation
		const getClientIP = (req: any, headers: IDataObject): string => {
			return (
				headers['x-forwarded-for'] as string ||
				headers['x-real-ip'] as string ||
				headers['x-client-ip'] as string ||
				req.connection?.remoteAddress ||
				req.socket?.remoteAddress ||
				req.ip ||
				'unknown'
			)?.split(',')[0]?.trim();
		};

		const isIpAllowed = (clientIp: string, allowedRange: string): boolean => {
			if (!clientIp || clientIp === 'unknown') return false;
			
			if (!allowedRange.includes('/')) {
				return clientIp === allowedRange;
			}

			try {
				const [network, prefixLength] = allowedRange.split('/');
				const networkInt = network.split('.').reduce((acc, octet) => (acc << 8) + parseInt(octet), 0) >>> 0;
				const clientInt = clientIp.split('.').reduce((acc, octet) => (acc << 8) + parseInt(octet), 0) >>> 0;
				const mask = (-1 << (32 - parseInt(prefixLength))) >>> 0;

				return (networkInt & mask) === (clientInt & mask);
			} catch (error) {
				return false;
			}
		};

		const verifyHmacSignature = (payload: string, signature: string, secret: string): boolean => {
			try {
				const cleanSignature = signature.replace(/^sha256=/, '');
				const expectedSignature = createHmac('sha256', secret)
					.update(payload, 'utf8')
					.digest('hex');

				const signatureBuffer = Buffer.from(cleanSignature, 'hex');
				const expectedBuffer = Buffer.from(expectedSignature, 'hex');

				return signatureBuffer.length === expectedBuffer.length && 
					   timingSafeEqual(signatureBuffer, expectedBuffer);
			} catch (error) {
				return false;
			}
		};

		const sanitizeHeaders = (headers: IDataObject): IDataObject => {
			const sanitized = { ...headers };
			delete sanitized['x-n8n-api-key'];
			delete sanitized['authorization'];
			delete sanitized['x-laravel-signature'];
			return sanitized;
		};

		// Security validations
		try {
			// 1. IP Address validation
			const expectedIp = nodeParameters.expectedSourceIp || '';
			console.log('📨 Expected IP:', expectedIp);
			
			if (expectedIp && expectedIp.trim() !== '') {
				const clientIp = getClientIP(req, headers);
				console.log('📨 Client IP:', clientIp);
				
				if (!isIpAllowed(clientIp, expectedIp)) {
					throw new NodeOperationError(this.getNode(), 
						`Webhook rejected: IP ${clientIp} not in allowed range ${expectedIp}`
					);
				}
			}

			// 2. HMAC Signature verification
			const verifyHmac = nodeParameters.verifyHmac || false;
			console.log('📨 Verify HMAC:', verifyHmac);
			
			if (verifyHmac && credentials.hmacSecret) {
				const signature = headers['x-laravel-signature'] as string;
				if (!signature) {
					throw new NodeOperationError(this.getNode(), 
						'Webhook rejected: Missing HMAC signature header'
					);
				}

				const isValidSignature = verifyHmacSignature(
					JSON.stringify(bodyData),
					signature,
					credentials.hmacSecret as string
				);

				if (!isValidSignature) {
					throw new NodeOperationError(this.getNode(), 
						'Webhook rejected: Invalid HMAC signature'
					);
				}
			}

			// 3. Timestamp validation (replay attack prevention)
			const requireTimestamp = nodeParameters.requireTimestamp || false;
			console.log('📨 Require timestamp:', requireTimestamp);
			
			if (requireTimestamp && bodyData.timestamp) {
				const webhookTime = new Date(bodyData.timestamp as string).getTime();
				const currentTime = Date.now();
				const maxAge = 5 * 60 * 1000; // 5 minutes

				if (currentTime - webhookTime > maxAge) {
					throw new NodeOperationError(this.getNode(), 
						'Webhook rejected: Timestamp too old (replay attack prevention)'
					);
				}
			}

			// 4. Model and event validation
			const configuredModel = nodeParameters.model || '';
			const configuredEvents = nodeParameters.events || [];
			console.log('📨 Configured model:', configuredModel);
			console.log('📨 Configured events:', configuredEvents);
			
			if (bodyData.model && configuredModel && bodyData.model !== configuredModel) {
				throw new NodeOperationError(this.getNode(), 
					`Webhook rejected: Model mismatch. Expected ${configuredModel}, got ${bodyData.model}`
				);
			}

			if (bodyData.event && configuredEvents.length > 0 && !configuredEvents.includes(bodyData.event as string)) {
				throw new NodeOperationError(this.getNode(), 
					`Webhook rejected: Event ${bodyData.event} not in configured events`
				);
			}

			console.log('✅ All security validations passed');

		} catch (error) {
			// Log security violations for monitoring
			console.error('Laravel Eloquent Trigger Security Violation:', {
				error: error instanceof Error ? error.message : String(error),
				ip: getClientIP(req, headers),
				timestamp: new Date().toISOString(),
				headers: sanitizeHeaders(headers),
			});
			
			throw error;
		}

		console.log('🎉 Webhook processing successful, returning data');

		// Return successful webhook data
		return {
			workflowData: [
				[
					{
						json: {
							...bodyData,
							security: {
								verified: true,
								timestamp: new Date().toISOString(),
								sourceIp: getClientIP(req, headers),
							},
							headers: sanitizeHeaders(headers),
						},
					},
				],
			],
		};
	}
} 