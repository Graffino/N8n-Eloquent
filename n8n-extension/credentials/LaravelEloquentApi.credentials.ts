import {
	IAuthenticateGeneric,
	ICredentialTestRequest,
	ICredentialType,
	INodeProperties,
} from 'n8n-workflow';

export class LaravelEloquentApi implements ICredentialType {
	name = 'laravelEloquentApi';
	displayName = 'Laravel Eloquent API';
	documentationUrl = 'https://github.com/n8n-io/n8n-eloquent';
	properties: INodeProperties[] = [
		{
			displayName: 'Base URL',
			name: 'baseUrl',
			type: 'string',
			default: '',
			placeholder: 'https://your-laravel-app.com',
			description: 'The base URL of your Laravel application',
			required: true,
		},
		{
			displayName: 'API Key',
			name: 'apiKey',
			type: 'string',
			typeOptions: {
				password: true,
			},
			default: '',
			description: 'The API key for Laravel Eloquent integration',
			required: true,
		},
		{
			displayName: 'HMAC Secret',
			name: 'hmacSecret',
			type: 'string',
			typeOptions: {
				password: true,
			},
			default: '',
			description: 'The HMAC secret for webhook signature verification (optional)',
			required: false,
		},
	];

	authenticate: IAuthenticateGeneric = {
		type: 'generic',
		properties: {
			headers: {
				'X-N8n-Api-Key': '={{$credentials.apiKey}}',
				'Content-Type': 'application/json',
				'Accept': 'application/json',
			},
		},
	};

	test: ICredentialTestRequest = {
		request: {
			baseURL: '={{$credentials.baseUrl}}',
			url: '/api/n8n/models',
			method: 'GET',
		},
	};
} 