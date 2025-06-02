import {
	IExecuteFunctions,
	IDataObject,
	INodeExecutionData,
	INodeType,
	INodeTypeDescription,
	NodeConnectionType,
	NodeOperationError,
	IHttpRequestMethods,
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
		description: 'Retrieve data from Laravel Eloquent models',
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
						name: 'Get All Records',
						value: 'getAll',
						description: 'Retrieve all records from a model',
						action: 'Get all records from a model',
					},
					{
						name: 'Get Record by ID',
						value: 'getById',
						description: 'Retrieve a specific record by its ID',
						action: 'Get a record by ID from a model',
					},
				],
				default: 'getAll',
			},
			{
				displayName: 'Model',
				name: 'model',
				type: 'options',
				typeOptions: {
					loadOptionsMethod: 'getModels',
				},
				default: '',
				required: true,
				description: 'The Laravel Eloquent model to query',
			},
			{
				displayName: 'Record ID',
				name: 'recordId',
				type: 'string',
				displayOptions: {
					show: {
						operation: ['getById'],
					},
				},
				default: '',
				required: true,
				description: 'The ID of the record to retrieve',
			},
			{
				displayName: 'Limit',
				name: 'limit',
				type: 'number',
				displayOptions: {
					show: {
						operation: ['getAll'],
					},
				},
				typeOptions: {
					minValue: 1,
					maxValue: 1000,
				},
				default: 50,
				description: 'Maximum number of records to return',
			},
			{
				displayName: 'Offset',
				name: 'offset',
				type: 'number',
				displayOptions: {
					show: {
						operation: ['getAll'],
					},
				},
				typeOptions: {
					minValue: 0,
				},
				default: 0,
				description: 'Number of records to skip',
			},
			{
				displayName: 'Additional Fields',
				name: 'additionalFields',
				type: 'collection',
				placeholder: 'Add Field',
				default: {},
				options: [
					{
						displayName: 'Where Conditions',
						name: 'where',
						type: 'fixedCollection',
						typeOptions: {
							multipleValues: true,
						},
						default: {},
						options: [
							{
								name: 'conditions',
								displayName: 'Condition',
								values: [
									{
										displayName: 'Field',
										name: 'field',
										type: 'string',
										default: '',
										description: 'The field name to filter by',
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
												value: 'LIKE',
											},
											{
												name: 'In',
												value: 'IN',
											},
										],
										default: '=',
									},
									{
										displayName: 'Value',
										name: 'value',
										type: 'string',
										default: '',
										description: 'The value to compare against',
									},
								],
							},
						],
					},
					{
						displayName: 'Order By',
						name: 'orderBy',
						type: 'fixedCollection',
						typeOptions: {
							multipleValues: true,
						},
						default: {},
						options: [
							{
								name: 'orders',
								displayName: 'Order',
								values: [
									{
										displayName: 'Field',
										name: 'field',
										type: 'string',
										default: '',
										description: 'The field name to order by',
									},
									{
										displayName: 'Direction',
										name: 'direction',
										type: 'options',
										options: [
											{
												name: 'Ascending',
												value: 'asc',
											},
											{
												name: 'Descending',
												value: 'desc',
											},
										],
										default: 'asc',
									},
								],
							},
						],
					},
				],
			},
		],
	};

	methods = {
		loadOptions: {
			// Load available models from Laravel API
			async getModels(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					
					const options = {
						method: 'GET' as IHttpRequestMethods,
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-N8n-Api-Key': credentials.apiKey as string,
						},
						uri: `${credentials.baseUrl}/api/n8n/models`,
						json: true,
					};

					const response = await this.helpers.request(options);
					
					if (response.models && Array.isArray(response.models)) {
						return response.models.map((model: any) => ({
							name: model.name || model.class.split('\\').pop(),
							value: model.class,
							description: `Table: ${model.table} | Primary Key: ${model.primaryKey}`,
						}));
					}
					
					return [];
				} catch (error) {
					console.error('Failed to load models:', error);
					// Return empty array if API call fails
					return [];
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
				const credentials = await this.getCredentials('laravelEloquentApi', i);

				let endpoint = '';
				const queryParams: IDataObject = {};

				// Build endpoint and query parameters based on operation
				switch (operation) {
					case 'getAll':
						endpoint = `/api/n8n/models/${encodeURIComponent(model)}/records`;
						const limit = this.getNodeParameter('limit', i) as number;
						const offset = this.getNodeParameter('offset', i) as number;
						
						if (limit) {
							queryParams.limit = limit;
						}
						if (offset) {
							queryParams.offset = offset;
						}

						// Add where conditions
						const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;
						if (additionalFields.where && typeof additionalFields.where === 'object' && 
							(additionalFields.where as IDataObject).conditions) {
							queryParams.where = (additionalFields.where as IDataObject).conditions;
						}

						// Add order by
						if (additionalFields.orderBy && typeof additionalFields.orderBy === 'object' && 
							(additionalFields.orderBy as IDataObject).orders) {
							queryParams.orderBy = (additionalFields.orderBy as IDataObject).orders;
						}
						break;

					case 'getById':
						const recordId = this.getNodeParameter('recordId', i) as string;
						endpoint = `/api/n8n/models/${encodeURIComponent(model)}/records/${encodeURIComponent(recordId)}`;
						break;

					default:
						throw new NodeOperationError(this.getNode(), `Unknown operation: ${operation}`, {
							itemIndex: i,
						});
				}

				// Make the API request
				const options = {
					method: 'GET' as const,
					url: `${credentials.baseUrl}${endpoint}`,
					headers: {
						'X-N8n-Api-Key': credentials.apiKey,
						'Content-Type': 'application/json',
						'Accept': 'application/json',
					},
					qs: queryParams,
					json: true,
				};

				const response = await this.helpers.request(options);

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