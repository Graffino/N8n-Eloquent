import {
	IAuthenticateGeneric,
	ICredentialTestRequest,
	ICredentialType,
	INodeProperties,
} from 'n8n-workflow';

export class LaravelEloquentApi implements ICredentialType {
	name = 'laravelEloquentApi';
	displayName = 'Laravel Eloquent API';
	documentationUrl = 'https://laravel.com/docs/eloquent';
	properties: INodeProperties[] = [
		{
			displayName: 'Base URL',
			name: 'baseUrl',
			type: 'string',
			default: 'http://localhost:8000',
			placeholder: 'https://your-laravel-app.com',
			description: 'The base URL of your Laravel application',
		},
		{
			displayName: 'API Key',
			name: 'apiKey',
			type: 'string',
			typeOptions: { password: true },
			default: '',
			description: 'The API key for authenticating with your Laravel application',
		},
		{
			displayName: 'HMAC Secret',
			name: 'hmacSecret',
			type: 'string',
			typeOptions: { password: true },
			default: '',
			description: 'Optional: HMAC secret for webhook signature verification',
		},
	];

	authenticate: IAuthenticateGeneric = {
		type: 'generic',
		properties: {
			headers: {
				'X-N8n-Api-Key': '={{$credentials.apiKey}}',
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