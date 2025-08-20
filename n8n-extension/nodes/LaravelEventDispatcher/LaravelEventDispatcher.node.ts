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
	workflow_id?: string;
	node_id?: string;
	execution_id?: string;
	is_n8n_event_dispatch?: boolean;
}

export class LaravelEventDispatcher implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Laravel Event Dispatcher',
		name: 'laravelEventDispatcher',
		icon: 'file:laravel.svg',
		group: ['transform'],
		version: 1,
		subtitle: '={{$parameter["event"]}}',
		description: 'Dispatch Laravel events from n8n workflows',
		defaults: {
			name: 'Laravel Event Dispatcher',
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
				displayName: 'Event',
				name: 'event',
				type: 'options',
				typeOptions: {
					loadOptionsMethod: 'getEvents',
				},
				default: '',
				required: true,
				description: 'The Laravel event class to dispatch',
			},
			// Event parameters
			{
				displayName: 'Event Parameters',
				name: 'parameters',
				type: 'fixedCollection',
				typeOptions: {
					multipleValues: true,
				},
				default: {},
				options: [
					{
						name: 'parameterValues',
						displayName: 'Parameter',
						values: [
							{
								displayName: 'Parameter Name',
								name: 'parameterName',
								type: 'options',
								typeOptions: {
									loadOptionsMethod: 'getEventParameters',
									loadOptionsDependsOn: ['event'],
								},
								default: '',
								description: 'Name of the event parameter',
							},
							{
								displayName: 'Parameter Value',
								name: 'parameterValue',
								type: 'string',
								default: '',
								description: 'Value of the event parameter',
							},
						],
					},
				],
				description: 'Parameters to pass to the event',
			},
			// Additional options
			{
				displayName: 'Additional Fields',
				name: 'additionalFields',
				type: 'collection',
				placeholder: 'Add Field',
				default: {},
				options: [
					{
						displayName: 'Custom Metadata',
						name: 'customMetadata',
						type: 'fixedCollection',
						typeOptions: {
							multipleValues: true,
						},
						default: {},
						options: [
							{
								name: 'metadataValues',
								displayName: 'Metadata',
								values: [
									{
										displayName: 'Key',
										name: 'key',
										type: 'string',
										default: '',
										description: 'Metadata key',
									},
									{
										displayName: 'Value',
										name: 'value',
										type: 'string',
										default: '',
										description: 'Metadata value',
									},
								],
							},
						],
						description: 'Additional metadata to include with the event',
					},
				],
			},
		],
	};

	methods = {
		loadOptions: {
			// Load available events from Laravel API
			async getEvents(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					
					const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'GET',
						url: `${baseUrl}/api/n8n/events`,
						json: true,
						skipSslCertificateValidation: true,
					});

					const events = response.events.map((event: any) => ({
						name: event.name,
						value: event.class,
						description: `Full class: ${event.class}`,
					}));
					return events;
				} catch (error) {
					console.error('❌ Failed to load events:', error);
					
					throw new NodeOperationError(this.getNode(), `Failed to load events: ${(error as Error).message}`);
				}
			},
			// Load event parameters
			async getEventParameters(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					const event = this.getNodeParameter('event') as string;

					if (!event) {
						throw new NodeOperationError(this.getNode(), 'Please select an event first');
					}

					// Encode the event name properly for the URL
					const encodedEvent = encodeURIComponent(event);
					const url = `${baseUrl}/api/n8n/events/${encodedEvent}/parameters`;

					try {
						const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
							method: 'GET',
							url,
							json: true,
							skipSslCertificateValidation: true,
						});

						if (!response.parameters || !Array.isArray(response.parameters)) {
							throw new NodeOperationError(
								this.getNode(),
								'Invalid response format from API. Expected parameters array.'
							);
						}

						return response.parameters.map((param: any) => ({
							name: param.label || param.name,
							value: param.name,
							description: `Type: ${param.type}${param.required ? ' (required)' : ''}`,
						}));
					} catch (httpError: any) {
						console.error('❌ Failed to load event parameters:', {
							status: httpError.response?.status,
							statusText: httpError.response?.statusText,
							data: httpError.response?.data,
							error: httpError.message,
						});

						throw new NodeOperationError(
							this.getNode(),
							`Failed to load event parameters: ${httpError.message}`,
							{
								description: httpError.response?.data?.message 
									|| 'Could not connect to Laravel API. Please check your credentials and ensure the API is running.',
							}
						);
					}
				} catch (error: any) {
					console.error('❌ Failed to load event parameters:', error);
					
					throw error;
				}
			},
		},
	};

	async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
		const returnData: INodeExecutionData[] = [];
		
		const credentials = await this.getCredentials('laravelEloquentApi');
		const baseUrl = credentials.baseUrl as string;
		const event = this.getNodeParameter('event', 0) as string;

		// Get workflow execution context
		const workflowId = this.getWorkflow().id;
		const nodeId = this.getNode().id;
		const executionId = this.getExecutionId();

		// Add metadata to track n8n operations
		const metadata: IMetadata = {
			workflow_id: workflowId,
			node_id: nodeId,
			execution_id: executionId,
			is_n8n_event_dispatch: true,
		};

		try {
			let responseData: IDataObject = {};
			
			// Construct the base URL for event operations
			const eventApiUrl = `${baseUrl}/api/n8n/events/${encodeURIComponent(event)}/dispatch`;

			// Prepare event data
			const parameters = this.getNodeParameter('parameters.parameterValues', 0, []) as IDataObject[];
			const eventData: IDataObject = {};
			
			for (const param of parameters) {
				eventData[param.parameterName as string] = param.parameterValue;
			}

			// Add metadata to the request
			eventData.metadata = metadata;
			
			// Add custom metadata if provided
			const additionalFields = this.getNodeParameter('additionalFields', 0) as IDataObject;
			const customMetadata = additionalFields.customMetadata as IDataObject;
			if (customMetadata?.metadataValues) {
				const metadataValues = customMetadata.metadataValues as IDataObject[];
				for (const meta of metadataValues) {
					if (meta.key && typeof meta.key === 'string') {
						(eventData.metadata as IDataObject)[meta.key] = meta.value;
					}
				}
			}

			const options: IHttpRequestOptions = {
				method: 'POST' as IHttpRequestMethods,
				url: eventApiUrl,
				body: eventData,
				json: true,
				skipSslCertificateValidation: true,
			};

			responseData = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', options);

			returnData.push({ json: responseData });

			return [returnData];
		} catch (error: any) {
			console.error('❌ Error processing event dispatch:', error);
			
			if (error.response) {
				throw new NodeOperationError(this.getNode(), `API Error: ${error.response.data?.error || error.message}`, {
					description: error.response.data?.message || 'Unknown API error',
				});
			}
			throw error;
		}
	}
} 