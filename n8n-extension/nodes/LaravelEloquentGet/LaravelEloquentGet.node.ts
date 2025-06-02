import {
	IExecuteFunctions,
	IDataObject,
	INodeType,
	INodeTypeDescription,
	INodeExecutionData,
	NodeConnectionType,
	NodeOperationError,
	ILoadOptionsFunctions,
	INodePropertyOptions,
} from 'n8n-workflow';

export class LaravelEloquentGet implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Laravel Eloquent Get',
		name: 'laravelEloquentGet',
		icon: 'file:laravel.svg',
		group: ['input'],
		version: 1,
		subtitle: '={{$parameter["operation"] + ": " + $parameter["model"]}}',
		description: 'Get data from Laravel Eloquent models',
		defaults: {
			name: 'Laravel Eloquent Get',
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
						name: 'Get All',
						value: 'getAll',
						description: 'Get all records from a model',
						action: 'Get all records',
					},
					{
						name: 'Get by ID',
						value: 'getById',
						description: 'Get a specific record by ID',
						action: 'Get record by ID',
					},
					{
						name: 'Search',
						value: 'search',
						description: 'Search records with filters',
						action: 'Search records',
					},
				],
				default: 'getAll',
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
						operation: ['getById'],
					},
				},
				description: 'The ID of the record to retrieve',
			},
			{
				displayName: 'Limit',
				name: 'limit',
				type: 'number',
				default: 50,
				displayOptions: {
					show: {
						operation: ['getAll', 'search'],
					},
				},
				description: 'Maximum number of records to return',
			},
			{
				displayName: 'Filters',
				name: 'filters',
				type: 'fixedCollection',
				typeOptions: {
					multipleValues: true,
				},
				default: {},
				displayOptions: {
					show: {
						operation: ['search'],
					},
				},
				options: [
					{
						name: 'filter',
						displayName: 'Filter',
						values: [
							{
								displayName: 'Field',
								name: 'field',
								type: 'string',
								default: '',
								description: 'Field name to filter by',
							},
							{
								displayName: 'Operator',
								name: 'operator',
								type: 'options',
								options: [
									{
										name: 'Equals',
										value: '=',
									},
									{
										name: 'Not Equals',
										value: '!=',
									},
									{
										name: 'Greater Than',
										value: '>',
									},
									{
										name: 'Greater Than or Equal',
										value: '>=',
									},
									{
										name: 'Less Than',
										value: '<',
									},
									{
										name: 'Less Than or Equal',
										value: '<=',
									},
									{
										name: 'Like',
										value: 'like',
									},
									{
										name: 'In',
										value: 'in',
									},
								],
								default: '=',
								description: 'Comparison operator',
							},
							{
								displayName: 'Value',
								name: 'value',
								type: 'string',
								default: '',
								description: 'Value to compare against',
							},
						],
					},
				],
			},
			{
				displayName: 'Include Relationships',
				name: 'with',
				type: 'string',
				default: '',
				placeholder: 'posts,comments',
				description: 'Comma-separated list of relationships to include',
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

	async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
		const items = this.getInputData();
		const returnData: INodeExecutionData[] = [];

		for (let i = 0; i < items.length; i++) {
			let operation = '';
			let model = '';
			
			try {
				operation = this.getNodeParameter('operation', i) as string;
				model = this.getNodeParameter('model', i) as string;

				let endpoint = '';
				const queryParams: IDataObject = {};

				// Build endpoint and query parameters based on operation
				switch (operation) {
					case 'getAll':
						endpoint = `/api/n8n/models/${encodeURIComponent(model)}`;
						const limit = this.getNodeParameter('limit', i) as number;
						if (limit) {
							queryParams.limit = limit;
						}
						break;

					case 'getById':
						const recordId = this.getNodeParameter('recordId', i) as string;
						endpoint = `/api/n8n/models/${encodeURIComponent(model)}/${encodeURIComponent(recordId)}`;
						break;

					case 'search':
						endpoint = `/api/n8n/models/${encodeURIComponent(model)}`;
						const searchLimit = this.getNodeParameter('limit', i) as number;
						if (searchLimit) {
							queryParams.limit = searchLimit;
						}

						// Add filters
						const filters = this.getNodeParameter('filters', i) as IDataObject;
						if (filters.filter && Array.isArray(filters.filter)) {
							queryParams.filters = filters.filter;
						}
						break;

					default:
						throw new NodeOperationError(this.getNode(), `Unknown operation: ${operation}`, {
							itemIndex: i,
						});
				}

				// Add relationships
				const withRelations = this.getNodeParameter('with', i) as string;
				if (withRelations) {
					queryParams.with = withRelations;
				}

				// Make the authenticated API request
				const credentials = await this.getCredentials('laravelEloquentApi');
				const baseUrl = credentials.baseUrl as string;
				
				const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
					method: 'GET',
					url: `${baseUrl}${endpoint}`,
					qs: queryParams,
					json: true,
				});

				// Handle the response
				if (operation === 'getById') {
					// Single record
					returnData.push({
						json: response.data || response,
						pairedItem: { item: i },
					});
				} else {
					// Multiple records
					const records = response.data || response;
					if (Array.isArray(records)) {
						records.forEach((record: IDataObject) => {
							returnData.push({
								json: record,
								pairedItem: { item: i },
							});
						});
					} else {
						returnData.push({
							json: records,
							pairedItem: { item: i },
						});
					}
				}
			} catch (error) {
				// Enhanced error handling with security logging
				const errorMessage = error instanceof Error ? error.message : String(error);
				const isAuthError = errorMessage.includes('401') || errorMessage.includes('403') || errorMessage.includes('Unauthorized');
				
				// Log security-related errors
				if (isAuthError) {
					console.error('Laravel Eloquent Get Authentication Error:', {
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