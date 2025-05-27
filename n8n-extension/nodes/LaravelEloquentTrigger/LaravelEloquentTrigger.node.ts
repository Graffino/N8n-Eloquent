import {
	IWebhookFunctions,
	IDataObject,
	INodeType,
	INodeTypeDescription,
	IWebhookResponseData,
	NodeConnectionType,
	NodeOperationError,
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
				type: 'string',
				default: '',
				required: true,
				placeholder: 'App\\Models\\User',
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


} 