import {
	IExecuteFunctions,
	IDataObject,
	INodeType,
	INodeTypeDescription,
	INodeExecutionData,
	NodeConnectionType,
	NodeOperationError,
} from 'n8n-workflow';

export class LaravelEloquentSet implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Laravel Eloquent Set',
		name: 'laravelEloquentSet',
		icon: 'file:laravel.svg',
		group: ['output'],
		version: 1,
		subtitle: '={{$parameter["operation"] + ": " + $parameter["model"]}}',
		description: 'Create, update, or delete Laravel Eloquent model records',
		defaults: {
			name: 'Laravel Eloquent Set',
		},
		inputs: [NodeConnectionType.Main],
		outputs: [NodeConnectionType.Main],
		credentials: [
			{
				name: 'laravelEloquentApi',
				required: true,
			},
		],
		properties: [
			{
				displayName: 'Operation',
				name: 'operation',
				type: 'options',
				noDataExpression: true,
				options: [
					{
						name: 'Create',
						value: 'create',
						description: 'Create a new record',
						action: 'Create a record',
					},
					{
						name: 'Update',
						value: 'update',
						description: 'Update an existing record',
						action: 'Update a record',
					},
					{
						name: 'Upsert',
						value: 'upsert',
						description: 'Create or update a record',
						action: 'Upsert a record',
					},
					{
						name: 'Delete',
						value: 'delete',
						description: 'Delete a record',
						action: 'Delete a record',
					},
				],
				default: 'create',
			},
			{
				displayName: 'Model',
				name: 'model',
				type: 'string',
				default: '',
				required: true,
				placeholder: 'App\\Models\\User',
				description: 'The Laravel Eloquent model class',
			},
			{
				displayName: 'Record ID',
				name: 'recordId',
				type: 'string',
				default: '',
				required: true,
				displayOptions: {
					show: {
						operation: ['update', 'delete'],
					},
				},
				description: 'The ID of the record to update or delete',
			},
			{
				displayName: 'Data Source',
				name: 'dataSource',
				type: 'options',
				options: [
					{
						name: 'Define Below',
						value: 'defineBelow',
						description: 'Set the data manually',
					},
					{
						name: 'JSON',
						value: 'json',
						description: 'Use JSON data from input',
					},
				],
				default: 'defineBelow',
				displayOptions: {
					show: {
						operation: ['create', 'update', 'upsert'],
					},
				},
			},
			{
				displayName: 'JSON Data',
				name: 'jsonData',
				type: 'json',
				default: '{}',
				displayOptions: {
					show: {
						operation: ['create', 'update', 'upsert'],
						dataSource: ['json'],
					},
				},
				description: 'JSON object containing the data to save',
			},
			{
				displayName: 'Fields',
				name: 'fields',
				type: 'fixedCollection',
				typeOptions: {
					multipleValues: true,
				},
				default: {},
				displayOptions: {
					show: {
						operation: ['create', 'update', 'upsert'],
						dataSource: ['defineBelow'],
					},
				},
				options: [
					{
						name: 'field',
						displayName: 'Field',
						values: [
							{
								displayName: 'Field Name',
								name: 'name',
								type: 'string',
								default: '',
								description: 'Name of the field',
							},
							{
								displayName: 'Field Value',
								name: 'value',
								type: 'string',
								default: '',
								description: 'Value of the field',
							},
						],
					},
				],
			},
			{
				displayName: 'Upsert Key',
				name: 'upsertKey',
				type: 'string',
				default: 'id',
				displayOptions: {
					show: {
						operation: ['upsert'],
					},
				},
				description: 'Field to use for checking if record exists (for upsert operation)',
			},
		],
	};

	async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
		const items = this.getInputData();
		const returnData: INodeExecutionData[] = [];

		for (let i = 0; i < items.length; i++) {
			let operation = '';
			let model = '';
			
			try {
				operation = this.getNodeParameter('operation', i) as string;
				model = this.getNodeParameter('model', i) as string;
				const credentials = await this.getCredentials('laravelEloquentApi', i);

				let endpoint = '';
				let method: 'GET' | 'POST' | 'PUT' | 'DELETE' = 'POST';
				let body: IDataObject = {};

				// Helper function to build data object
				const buildDataObject = (): IDataObject => {
					const dataSource = this.getNodeParameter('dataSource', i) as string;

					if (dataSource === 'json') {
						const jsonData = this.getNodeParameter('jsonData', i) as string;
						try {
							return JSON.parse(jsonData);
						} catch (error) {
							throw new NodeOperationError(this.getNode(), 'Invalid JSON data', {
								itemIndex: i,
							});
						}
					} else {
						// Build from fields
						const fields = this.getNodeParameter('fields', i) as IDataObject;
						const data: IDataObject = {};

						if (fields.field && Array.isArray(fields.field)) {
							fields.field.forEach((field: IDataObject) => {
								if (field.name && field.value !== undefined) {
									data[field.name as string] = field.value;
								}
							});
						}

						return data;
					}
				};

				// Build endpoint and request based on operation
				switch (operation) {
					case 'create':
						endpoint = `/api/n8n/models/${encodeURIComponent(model)}`;
						method = 'POST';
						body = buildDataObject();
						break;

					case 'update':
						const updateId = this.getNodeParameter('recordId', i) as string;
						endpoint = `/api/n8n/models/${encodeURIComponent(model)}/${encodeURIComponent(updateId)}`;
						method = 'PUT';
						body = buildDataObject();
						break;

					case 'upsert':
						endpoint = `/api/n8n/models/${encodeURIComponent(model)}/upsert`;
						method = 'POST';
						body = buildDataObject();
						const upsertKey = this.getNodeParameter('upsertKey', i) as string;
						body.upsert_key = upsertKey;
						break;

					case 'delete':
						const deleteId = this.getNodeParameter('recordId', i) as string;
						endpoint = `/api/n8n/models/${encodeURIComponent(model)}/${encodeURIComponent(deleteId)}`;
						method = 'DELETE';
						break;

					default:
						throw new NodeOperationError(this.getNode(), `Unknown operation: ${operation}`, {
							itemIndex: i,
						});
				}

				// Make the API request
				const options = {
					method,
					url: `${credentials.baseUrl}${endpoint}`,
					headers: {
						'X-N8n-Api-Key': credentials.apiKey,
						'Content-Type': 'application/json',
						'Accept': 'application/json',
					},
					json: true,
					...(Object.keys(body).length > 0 && { body }),
				};

				const response = await this.helpers.request(options);

				// Handle the response
				returnData.push({
					json: response.data || response,
					pairedItem: { item: i },
				});

			} catch (error) {
				// Enhanced error handling with security logging
				const errorMessage = error instanceof Error ? error.message : String(error);
				const isAuthError = errorMessage.includes('401') || errorMessage.includes('403') || errorMessage.includes('Unauthorized');
				const isValidationError = errorMessage.includes('422') || errorMessage.includes('validation');
				
				// Log security-related errors
				if (isAuthError) {
					console.error('Laravel Eloquent Set Authentication Error:', {
						error: errorMessage,
						operation,
						model,
						timestamp: new Date().toISOString(),
						itemIndex: i,
					});
				}

				// Log validation errors for debugging
				if (isValidationError) {
					console.warn('Laravel Eloquent Set Validation Error:', {
						error: errorMessage,
						operation,
						model,
						timestamp: new Date().toISOString(),
						itemIndex: i,
					});
				}

				if (this.continueOnFail()) {
					returnData.push({
						json: { 
							error: errorMessage,
							operation,
							model,
							timestamp: new Date().toISOString(),
							isAuthError,
							isValidationError,
						},
						pairedItem: { item: i },
					});
					continue;
				}
				throw error;
			}
		}

		return [returnData];
	}


} 