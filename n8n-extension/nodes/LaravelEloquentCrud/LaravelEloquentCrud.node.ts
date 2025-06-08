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
	IHttpRequestOptions,
} from 'n8n-workflow';

interface IMetadata extends IDataObject {
	source_trigger?: {
		node_id: string;
		workflow_id: string;
		model: string;
		event: string;
		timestamp: string;
	} | undefined;
	workflow_id?: string;
	node_id?: string;
	execution_id?: string;
	is_n8n_crud?: boolean;
}

interface IItemMetadata {
	metadata?: {
		source_trigger?: IMetadata['source_trigger'];
	};
}

export class LaravelEloquentCrud implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Laravel Eloquent CRUD',
		name: 'laravelEloquentCrud',
		icon: 'file:laravel.svg',
		group: ['transform'],
		version: 1,
		subtitle: '={{$parameter["operation"] + ": " + $parameter["model"]}}',
		description: 'Perform CRUD operations on Laravel Eloquent models',
		defaults: {
			name: 'Laravel Eloquent CRUD',
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
						action: 'Create a new record in a model',
					},
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
					{
						name: 'Update',
						value: 'update',
						description: 'Update an existing record',
						action: 'Update a record in a model',
					},
					{
						name: 'Delete',
						value: 'delete',
						description: 'Delete a record',
						action: 'Delete a record from a model',
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
				description: 'The Laravel Eloquent model to operate on',
			},
			// Fields for Create/Update operations
			{
				displayName: 'Fields',
				name: 'fields',
				type: 'fixedCollection',
				typeOptions: {
					multipleValues: true,
				},
				displayOptions: {
					show: {
						operation: ['create', 'update'],
					},
				},
				default: {},
				options: [
					{
						name: 'fieldValues',
						displayName: 'Field',
						values: [
							{
								displayName: 'Field Name',
								name: 'fieldName',
								type: 'options',
								typeOptions: {
									loadOptionsMethod: 'getFields',
									loadOptionsDependsOn: ['model'],
								},
								default: '',
								description: 'Name of the field',
							},
							{
								displayName: 'Field Value',
								name: 'fieldValue',
								type: 'string',
								default: '',
								description: 'Value of the field',
							},
						],
					},
				],
				description: 'Fields to set on the record',
			},
			// ID field for Get/Update/Delete operations
			{
				displayName: 'Record ID',
				name: 'recordId',
				type: 'string',
				displayOptions: {
					show: {
						operation: ['getById', 'update', 'delete'],
					},
				},
				default: '',
				required: true,
				description: 'The ID of the record',
			},
			// Pagination for getAll
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
			// Additional query options
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
			// Add new getFields method
			async getFields(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				console.log('📋 getFields() called - Loading field options');
				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					const model = this.getNodeParameter('model') as string;

					console.log('🔑 Using credentials with baseUrl:', baseUrl);
					console.log('🌐 Making request to get fields for model:', model);

					const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'GET',
						url: `${baseUrl}/api/n8n/models/${model}/fields`,
						json: true,
					});

					console.log('✅ Fields response:', response);

					return response.fields.map((field: any) => ({
						name: field.label || field.name,
						value: field.name,
						description: `Type: ${field.type}${field.nullable ? ' (nullable)' : ''}`,
					}));
				} catch (error) {
					console.error('❌ Failed to load fields:', error);
					throw new NodeOperationError(this.getNode(), `Failed to load fields: ${(error as Error).message}`);
				}
			},
		},
	};

	async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
		const items = this.getInputData();
		const returnData: INodeExecutionData[] = [];
		
		const credentials = await this.getCredentials('laravelEloquentApi');
		const baseUrl = credentials.baseUrl as string;
		const model = this.getNodeParameter('model', 0) as string;
		const operation = this.getNodeParameter('operation', 0) as string;

		// Get workflow execution context
		const workflowId = this.getWorkflow().id;
		const nodeId = this.getNode().id;
		const executionId = this.getExecutionId();

		// Add metadata to track n8n operations
		const metadata: IMetadata = {
			workflow_id: workflowId,
			node_id: nodeId,
			execution_id: executionId,
			is_n8n_crud: true,
		};

		try {
			let responseData: IDataObject | IDataObject[] = [];
			
			// Construct the base URL for model operations
			const modelApiUrl = `${baseUrl}/api/n8n/models/${encodeURIComponent(model)}/records`;

			switch (operation) {
				case 'create': {
					const fields = this.getNodeParameter('fields.fieldValues', 0, []) as IDataObject[];
					const data: IDataObject = {};
					
					for (const field of fields) {
						data[field.fieldName as string] = field.fieldValue;
					}

					// Add metadata to the request
					data.metadata = metadata;

					const options: IHttpRequestOptions = {
						method: 'POST' as IHttpRequestMethods,
						url: modelApiUrl,
						body: data,
						json: true,
					};

					responseData = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', options);
					break;
				}

				case 'getAll': {
					const limit = this.getNodeParameter('limit', 0) as number;
					const offset = this.getNodeParameter('offset', 0) as number;

					const options: IHttpRequestOptions = {
						method: 'GET' as IHttpRequestMethods,
						url: modelApiUrl,
						qs: {
							limit,
							offset,
						},
						json: true,
					};

					responseData = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', options);
					break;
				}

				case 'getById': {
					const recordId = this.getNodeParameter('recordId', 0) as string;

					const options: IHttpRequestOptions = {
						method: 'GET' as IHttpRequestMethods,
						url: `${modelApiUrl}/${recordId}`,
						json: true,
					};

					responseData = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', options);
					break;
				}

				case 'update': {
					const recordId = this.getNodeParameter('recordId', 0) as string;
					const fields = this.getNodeParameter('fields.fieldValues', 0, []) as IDataObject[];
					const data: IDataObject = {};
					
					for (const field of fields) {
						data[field.fieldName as string] = field.fieldValue;
					}

					// Add metadata to the request
					data.metadata = metadata;

					const options: IHttpRequestOptions = {
						method: 'PUT' as IHttpRequestMethods,
						url: `${modelApiUrl}/${recordId}`,
						body: data,
						json: true,
					};

					responseData = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', options);
					break;
				}

				case 'delete': {
					const recordId = this.getNodeParameter('recordId', 0) as string;

					const options: IHttpRequestOptions = {
						method: 'DELETE' as IHttpRequestMethods,
						url: `${modelApiUrl}/${recordId}`,
						json: true,
					};

					responseData = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', options);
					break;
				}

				default:
					throw new NodeOperationError(this.getNode(), `Operation ${operation} not supported`);
			}

			// Handle response data
			if (Array.isArray(responseData)) {
				returnData.push(...responseData.map(item => ({ json: item })));
			} else {
				returnData.push({ json: responseData });
			}

			return [returnData];
		} catch (error: any) {
			if (error.response) {
				throw new NodeOperationError(this.getNode(), `API Error: ${error.response.data?.error || error.message}`, {
					description: error.response.data?.message || 'Unknown API error',
				});
			}
			throw error;
		}
	}
} 