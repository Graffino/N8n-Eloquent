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

export class LaravelEloquentCrud implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Laravel Eloquent CRUD',
		name: 'laravelEloquentCrud',
		icon: 'file:laravel.svg',
		group: ['input'],
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
								type: 'string',
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

					return response.models.map((model: any) => ({
						name: model.name,
						value: encodeURIComponent(model.class),
					}));
				} catch (error) {
					console.error('❌ Failed to load models:', error);
					throw new NodeOperationError(this.getNode(), `Failed to load models: ${(error as Error).message}`);
				}
			},
		},
	};

	async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
		const items = this.getInputData();
		const returnData: IDataObject[] = [];
		const length = items.length;
		const credentials = await this.getCredentials('laravelEloquentApi');

		const operation = this.getNodeParameter('operation', 0) as string;
		const model = this.getNodeParameter('model', 0) as string;

		for (let i = 0; i < length; i++) {
			try {
				let response: any;
				const baseUrl = `${credentials.baseUrl}/api/n8n/models/${encodeURIComponent(model)}`;

				if (operation === 'create') {
					// Handle Create operation
					const fields = this.getNodeParameter('fields.fieldValues', i, []) as IDataObject[];
					const data = fields.reduce((obj, field) => {
						obj[field.fieldName as string] = field.fieldValue;
						return obj;
					}, {} as IDataObject);

					response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'POST',
						url: `${baseUrl}/records`,
						body: data,
						json: true,
					});

				} else if (operation === 'getAll') {
					// Handle Get All operation
					const limit = this.getNodeParameter('limit', i) as number;
					const offset = this.getNodeParameter('offset', i) as number;
					const additionalFields = this.getNodeParameter('additionalFields', i) as IDataObject;

					const qs: IDataObject = {
						limit,
						offset,
					};

					if (additionalFields.where) {
						qs.where = (additionalFields.where as IDataObject).conditions;
					}

					if (additionalFields.orderBy) {
						qs.orderBy = (additionalFields.orderBy as IDataObject).orders;
					}

					response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'GET',
						url: `${baseUrl}/records`,
						qs,
						json: true,
					});

				} else if (operation === 'getById') {
					// Handle Get By ID operation
					const recordId = this.getNodeParameter('recordId', i) as string;

					response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'GET',
						url: `${baseUrl}/records/${recordId}`,
						json: true,
					});

				} else if (operation === 'update') {
					// Handle Update operation
					const recordId = this.getNodeParameter('recordId', i) as string;
					const fields = this.getNodeParameter('fields.fieldValues', i, []) as IDataObject[];
					const data = fields.reduce((obj, field) => {
						obj[field.fieldName as string] = field.fieldValue;
						return obj;
					}, {} as IDataObject);

					response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'PUT',
						url: `${baseUrl}/records/${recordId}`,
						body: data,
						json: true,
					});

				} else if (operation === 'delete') {
					// Handle Delete operation
					const recordId = this.getNodeParameter('recordId', i) as string;

					response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'DELETE',
						url: `${baseUrl}/records/${recordId}`,
						json: true,
					});
				}

				if (response !== undefined) {
					returnData.push(response);
				}
			} catch (error: any) {
				if (this.continueOnFail()) {
					returnData.push({ error: error.message });
					continue;
				}
				throw error;
			}
		}

		return [this.helpers.returnJsonArray(returnData)];
	}
} 