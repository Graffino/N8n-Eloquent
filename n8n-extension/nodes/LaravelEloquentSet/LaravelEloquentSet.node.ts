import {
	IExecuteFunctions,
	ILoadOptionsFunctions,
	INodeExecutionData,
	INodePropertyOptions,
	INodeType,
	INodeTypeDescription,
	NodeOperationError,
	NodeConnectionType,
} from 'n8n-workflow';

export class LaravelEloquentSet implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Laravel Eloquent Set',
		name: 'laravelEloquentSet',
		icon: 'file:laravel.svg',
		group: ['transform'],
		version: 1,
		subtitle: '={{$parameter["operation"] + ": " + $parameter["model"]}}',
		description: 'Create, update, or delete Laravel Eloquent models',
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
						description: 'Create a new model record',
						action: 'Create a model record',
					},
					{
						name: 'Update',
						value: 'update',
						description: 'Update an existing model record',
						action: 'Update a model record',
					},
					{
						name: 'Delete',
						value: 'delete',
						description: 'Delete a model record',
						action: 'Delete a model record',
					},
				],
				default: 'create',
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
				description: 'The Laravel Eloquent model to work with',
			},
			{
				displayName: 'Record ID',
				name: 'recordId',
				type: 'string',
				displayOptions: {
					show: {
						operation: ['update', 'delete'],
					},
				},
				default: '',
				required: true,
				description: 'The ID of the record to update or delete',
			},
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
				placeholder: 'Add Field',
				options: [
					{
						name: 'field',
						displayName: 'Field',
						values: [
							{
								displayName: 'Name',
								name: 'name',
								type: 'string',
								default: '',
								description: 'Field name',
							},
							{
								displayName: 'Value',
								name: 'value',
								type: 'string',
								default: '',
								description: 'Field value',
							},
						],
					},
				],
			},
		],
	};

	methods = {
		loadOptions: {
			async getModels(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				const credentials = await this.getCredentials('laravelEloquentApi');
				const baseUrl = credentials.baseUrl as string;
				const apiKey = credentials.apiKey as string;

				try {
					const response = await this.helpers.request({
						method: 'GET',
						url: `${baseUrl}/api/n8n/models`,
						headers: {
							'Authorization': `Bearer ${apiKey}`,
							'Content-Type': 'application/json',
						},
					});

					return response.models.map((model: any) => ({
						name: model.name,
						value: model.name,
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
			try {
				const operation = this.getNodeParameter('operation', i) as string;
				const model = this.getNodeParameter('model', i) as string;
				const credentials = await this.getCredentials('laravelEloquentApi');
				const baseUrl = credentials.baseUrl as string;
				const apiKey = credentials.apiKey as string;

				let endpoint = `${baseUrl}/api/n8n/models/${model}`;
				let method: 'GET' | 'POST' | 'PUT' | 'DELETE' = 'POST';
				let body: any = {};

				if (operation === 'create') {
					const fields = this.getNodeParameter('fields.field', i, []) as Array<{name: string, value: string}>;
					body = {};
					fields.forEach(field => {
						body[field.name] = field.value;
					});
				} else if (operation === 'update') {
					const recordId = this.getNodeParameter('recordId', i) as string;
					const fields = this.getNodeParameter('fields.field', i, []) as Array<{name: string, value: string}>;
					endpoint = `${baseUrl}/api/n8n/models/${model}/${recordId}`;
					method = 'PUT';
					body = {};
					fields.forEach(field => {
						body[field.name] = field.value;
					});
				} else if (operation === 'delete') {
					const recordId = this.getNodeParameter('recordId', i) as string;
					endpoint = `${baseUrl}/api/n8n/models/${model}/${recordId}`;
					method = 'DELETE';
				}

				const response = await this.helpers.request({
					method,
					url: endpoint,
					headers: {
						'Authorization': `Bearer ${apiKey}`,
						'Content-Type': 'application/json',
					},
					body,
				});

				returnData.push({
					json: response,
					pairedItem: { item: i },
				});

			} catch (error) {
				if (this.continueOnFail()) {
					returnData.push({
						json: { error: (error as Error).message },
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