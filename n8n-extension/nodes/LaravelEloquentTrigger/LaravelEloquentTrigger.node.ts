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
	ITriggerFunctions,
	ITriggerResponse,
} from 'n8n-workflow';

import { createHmac, timingSafeEqual } from 'crypto';

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
					});

					return response.models.map((model: any) => ({
						name: model.name,
						value: model.class,
					}));
				} catch (error) {
					throw new NodeOperationError(this.getNode(), `Failed to load models: ${(error as Error).message}`);
				}
			},
		},
	};

	// Webhook lifecycle methods for automatic registration/unregistration
	async webhookCheckForExistingData(this: IHookFunctions): Promise<boolean> {
		// This method is called when the workflow is activated
		// Return false to indicate we don't have existing data to process
		return false;
	}

	async webhookCreate(this: IHookFunctions): Promise<boolean> {
		// This method is called when the workflow is activated or saved
		// It should register the webhook with the Laravel application
		
		console.log('🔄 Laravel Eloquent webhook registration starting...');
		console.log('🔍 Node context:', {
			nodeId: this.getNode().id,
			nodeName: this.getNode().name,
			workflowId: this.getWorkflow().id,
		});
		
		try {
			const model = this.getNodeParameter('model') as string;
			const events = this.getNodeParameter('events') as string[];
			const webhookUrl = this.getNodeWebhookUrl('default');

			console.log('📋 Registration details:', {
				model: model,
				events: events,
				webhookUrl: webhookUrl,
			});

			// Validate required parameters
			if (!model) {
				throw new Error('Model parameter is required');
			}
			if (!events || events.length === 0) {
				throw new Error('At least one event must be selected');
			}
			if (!webhookUrl) {
				throw new Error('Webhook URL could not be generated');
			}

			const requestBody = {
				model: model,
				events: events,
				webhook_url: webhookUrl,
			};

			console.log('🌐 Making authenticated request to webhook subscription endpoint');
			console.log('📦 Request body:', requestBody);

			const credentials = await this.getCredentials('laravelEloquentApi');
			const baseUrl = credentials.baseUrl as string;

			const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
				method: 'POST',
				url: `${baseUrl}/api/n8n/webhooks/subscribe`,
				body: requestBody,
				json: true,
			});
			
			console.log('✅ Registration response:', response);
			
			// Store the subscription ID for later cleanup
			if (response.subscription && response.subscription.id) {
				const webhookData = this.getWorkflowStaticData('node');
				webhookData.subscriptionId = response.subscription.id;
				console.log('💾 Stored subscription ID:', response.subscription.id);
			} else {
				console.warn('⚠️ No subscription ID in response:', response);
			}
			
			console.log('🎉 Laravel Eloquent webhook registered successfully!');
			return true;
		} catch (error) {
			console.error('❌ Failed to register Laravel Eloquent webhook:', error);
			
			// Enhanced error logging
			if (error instanceof Error) {
				console.error('Error details:', {
					message: error.message,
					stack: error.stack,
				});
			}
			
			// Check if it's a network/HTTP error
			if (error && typeof error === 'object' && 'response' in error) {
				const httpError = error as any;
				console.error('HTTP Error details:', {
					status: httpError.response?.status,
					statusText: httpError.response?.statusText,
					data: httpError.response?.data,
					headers: httpError.response?.headers,
				});
			}
			
			const errorMessage = error instanceof Error ? error.message : String(error);
			throw new NodeOperationError(this.getNode(), `Failed to register webhook: ${errorMessage}`);
		}
	}

	async webhookDelete(this: IHookFunctions): Promise<boolean> {
		// This method is called when the workflow is deactivated or the node is deleted
		// It should unregister the webhook from the Laravel application
		
		const webhookData = this.getWorkflowStaticData('node');
		
		// Check if we have a subscription ID to delete
		if (!webhookData.subscriptionId) {
			console.log('No subscription ID found, skipping webhook deletion');
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
			});
			
			// Clear the subscription ID
			delete webhookData.subscriptionId;
			
			console.log('Laravel Eloquent webhook unregistered successfully');
			return true;
		} catch (error) {
			console.error('Failed to unregister Laravel Eloquent webhook:', error);
			// Don't throw error here as it might prevent workflow deactivation
			return true;
		}
	}

	async webhook(this: IWebhookFunctions): Promise<IWebhookResponseData> {
		const bodyData = this.getBodyData() as IDataObject;
		const headers = this.getHeaderData() as IDataObject;
		const req = this.getRequestObject();
		const credentials = await this.getCredentials('laravelEloquentApi');

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
			const expectedIp = this.getNodeParameter('expectedSourceIp') as string;
			if (expectedIp) {
				const clientIp = getClientIP(req, headers);
				if (!isIpAllowed(clientIp, expectedIp)) {
					throw new NodeOperationError(this.getNode(), 
						`Webhook rejected: IP ${clientIp} not in allowed range ${expectedIp}`
					);
				}
			}

			// 2. HMAC Signature verification
			const verifyHmac = this.getNodeParameter('verifyHmac') as boolean;
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
			const requireTimestamp = this.getNodeParameter('requireTimestamp') as boolean;
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
			const configuredModel = this.getNodeParameter('model') as string;
			const configuredEvents = this.getNodeParameter('events') as string[];
			
			if (bodyData.model && bodyData.model !== configuredModel) {
				throw new NodeOperationError(this.getNode(), 
					`Webhook rejected: Model mismatch. Expected ${configuredModel}, got ${bodyData.model}`
				);
			}

			if (bodyData.event && !configuredEvents.includes(bodyData.event as string)) {
				throw new NodeOperationError(this.getNode(), 
					`Webhook rejected: Event ${bodyData.event} not in configured events`
				);
			}

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

	// Main trigger method - required for n8n to recognize this as a trigger node
	async trigger(this: ITriggerFunctions): Promise<ITriggerResponse> {
		// For webhook-based triggers, we return the webhook lifecycle methods
		// This tells n8n that this trigger uses webhooks for activation
		return {
			closeFunction: async () => {
				// This will be called when the workflow is deactivated
				// The webhookDelete method will handle the actual cleanup
				console.log('🔄 Laravel Eloquent trigger deactivated');
			},
			manualTriggerFunction: async () => {
				// This is called when the user manually tests the trigger
				console.log('🔄 Laravel Eloquent trigger manual test');
				this.emit([
					this.helpers.returnJsonArray([
						{
							message: 'Manual trigger test - webhook is active and ready to receive Laravel model events',
							timestamp: new Date().toISOString(),
							test: true,
						},
					]),
				]);
			},
		};
	}
} 