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
	is_n8n_job_dispatch?: boolean;
}

export class LaravelJobDispatcher implements INodeType {
	description: INodeTypeDescription = {
		displayName: 'Laravel Job Dispatcher',
		name: 'laravelJobDispatcher',
		icon: 'file:laravel.svg',
		group: ['transform'],
		version: 1,
		subtitle: '={{$parameter["operation"] + ": " + $parameter["job"]}}',
		description: 'Dispatch Laravel jobs from n8n workflows',
		defaults: {
			name: 'Laravel Job Dispatcher',
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
						name: 'Dispatch Job',
						value: 'dispatch',
						description: 'Dispatch a job to the queue',
						action: 'Dispatch a job to Laravel queue',
					},
					{
						name: 'Dispatch Job Later',
						value: 'dispatchLater',
						description: 'Schedule a job to run later',
						action: 'Schedule a job to run at a specific time',
					},
					{
						name: 'Dispatch Job Sync',
						value: 'dispatchSync',
						description: 'Dispatch a job synchronously',
						action: 'Dispatch a job to run immediately',
					},
				],
				default: 'dispatch',
			},
			{
				displayName: 'Job',
				name: 'job',
				type: 'options',
				typeOptions: {
					loadOptionsMethod: 'getJobs',
				},
				default: '',
				required: true,
				description: 'The Laravel job class to dispatch',
			},
			// Job parameters
			{
				displayName: 'Job Parameters',
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
									loadOptionsMethod: 'getJobParameters',
									loadOptionsDependsOn: ['job'],
								},
								default: '',
								description: 'Name of the job parameter',
							},
							{
								displayName: 'Parameter Value',
								name: 'parameterValue',
								type: 'string',
								default: '',
								description: 'Value of the job parameter (supports both strings and JSON objects)',
							},
						],
					},
				],
				description: 'Parameters to pass to the job (supports both strings and JSON objects)',
			},
			// Queue selection
			{
				displayName: 'Queue',
				name: 'queue',
				type: 'string',
				default: 'default',
				description: 'The queue to dispatch the job to',
			},
			{
				displayName: 'Delay (seconds)',
				name: 'delay',
				type: 'number',
				displayOptions: {
					show: {
						operation: ['dispatchLater'],
					},
				},
				typeOptions: {
					minValue: 0,
				},
				default: 0,
				description: 'Number of seconds to delay the job execution',
			},
			{
				displayName: 'Additional Fields',
				name: 'additionalFields',
				type: 'collection',
				placeholder: 'Add Field',
				default: {},
				options: [
					{
						displayName: 'Connection',
						name: 'connection',
						type: 'string',
						default: '',
						description: 'The queue connection to use',
					},
					{
						displayName: 'After Commit',
						name: 'afterCommit',
						type: 'boolean',
						default: false,
						description: 'Whether to dispatch the job after the database transaction is committed',
					},
					{
						displayName: 'Max Attempts',
						name: 'maxAttempts',
						type: 'number',
						typeOptions: {
							minValue: 1,
						},
						default: 1,
						description: 'Maximum number of attempts for the job',
					},
					{
						displayName: 'Timeout',
						name: 'timeout',
						type: 'number',
						typeOptions: {
							minValue: 1,
						},
						default: 60,
						description: 'Timeout in seconds for the job',
					},
				],
			},
		],
	};

	methods = {
		loadOptions: {
			async getJobs(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					
					const response = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', {
						method: 'GET',
						url: `${baseUrl}/api/n8n/jobs`,
						json: true,
						skipSslCertificateValidation: true,
					});

					const jobs = response.jobs.map((job: any) => ({
						name: job.name.split('\\').pop(),
						value: job.class,
						description: `Full class: ${job.class}`,
					}));

					return jobs;
				} catch (error) {
					console.error('❌ Failed to load jobs:', error);
					
					throw new NodeOperationError(this.getNode(), `Failed to load jobs: ${(error as Error).message}`);
				}
			},
			async getJobParameters(this: ILoadOptionsFunctions): Promise<INodePropertyOptions[]> {
				try {
					const credentials = await this.getCredentials('laravelEloquentApi');
					const baseUrl = credentials.baseUrl as string;
					const job = this.getNodeParameter('job') as string;

					if (!job) {
						throw new NodeOperationError(this.getNode(), 'Please select a job first');
					}

					const encodedJob = encodeURIComponent(job);
					const url = `${baseUrl}/api/n8n/jobs/${encodedJob}/parameters`;

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
						console.error('❌ Failed to load job parameters:', {
							status: httpError.response?.status,
							statusText: httpError.response?.statusText,
							data: httpError.response?.data,
							error: httpError.message,
						});
						
						throw new NodeOperationError(
							this.getNode(),
							`Failed to load job parameters: ${httpError.message}`,
							{
								description: httpError.response?.data?.message 
									|| 'Could not connect to Laravel API. Please check your credentials and ensure the API is running.',
							}
						);
					}
				} catch (error: any) {
					console.error('❌ Failed to load job parameters:', error);
					
					throw error;
				}
			},
		},
	};

	async execute(this: IExecuteFunctions): Promise<INodeExecutionData[][]> {
		const returnData: INodeExecutionData[] = [];
		
		const credentials = await this.getCredentials('laravelEloquentApi');
		const baseUrl = credentials.baseUrl as string;
		const job = this.getNodeParameter('job', 0) as string;
		const operation = this.getNodeParameter('operation', 0) as string;

		const workflowId = this.getWorkflow().id;
		const nodeId = this.getNode().id;
		const executionId = this.getExecutionId();

		const metadata: IMetadata = {
			workflow_id: workflowId,
			node_id: nodeId,
			execution_id: executionId,
			is_n8n_job_dispatch: true,
		};

		try {
			let responseData: IDataObject = {};
			
			const jobApiUrl = `${baseUrl}/api/n8n/jobs/${encodeURIComponent(job)}/dispatch`;

			const parameters = this.getNodeParameter('parameters.parameterValues', 0, []) as IDataObject[];
			const jobData: IDataObject = {};
			
			for (const param of parameters) {
				const paramName = param.parameterName as string;
				let paramValue = param.parameterValue;
				
				if (typeof paramValue === 'string' && (paramValue.startsWith('{') || paramValue.startsWith('['))) {
					try {
						paramValue = JSON.parse(paramValue);
					} catch (error) {
						console.error('❌ Failed to parse JSON for parameter:', error);
						// Keep as string if parsing fails
					}
				}
				
				jobData[paramName] = paramValue;
			}
			
			jobData.metadata = metadata;
			
			const additionalFields = this.getNodeParameter('additionalFields', 0) as IDataObject;
			const queue = this.getNodeParameter('queue', 0) as string;
			
			const requestData: IDataObject = {
				...jobData,
				queue,
				...additionalFields,
			};

			if (operation === 'dispatchLater') {
				const delay = this.getNodeParameter('delay', 0) as number;
				requestData.delay = delay;
			}

			const options: IHttpRequestOptions = {
				method: 'POST' as IHttpRequestMethods,
				url: jobApiUrl,
				body: requestData,
				json: true,
				skipSslCertificateValidation: true,
			};

			responseData = await this.helpers.httpRequestWithAuthentication.call(this, 'laravelEloquentApi', options);

			returnData.push({ json: responseData });

			return [returnData];
		} catch (error: any) {
			console.error('❌ Error processing job dispatch:', error);

			if (error.response) {
				throw new NodeOperationError(this.getNode(), `API Error: ${error.response.data?.error || error.message}`, {
					description: error.response.data?.message || 'Unknown API error',
				});
			}
			throw error;
		}
	}
} 