# Laravel Job Dispatcher Node

The Laravel Job Dispatcher Node allows you to dispatch Laravel jobs from your n8n workflows. This node provides a user-friendly interface to discover available jobs in your Laravel application and dispatch them with the appropriate parameters.

## Features

- **Configurable Job Discovery**: Only jobs explicitly configured in the n8n-eloquent config are available
- **Parameter Configuration**: Dynamically loads job parameters and provides a form to configure them
- **Queue Management**: Specify which queue to dispatch jobs to
- **Scheduling**: Dispatch jobs with delays for future execution
- **Advanced Options**: Configure connection, retry attempts, timeouts, and more

## Operations

### 1. Dispatch Job

Dispatches a job to the queue for immediate processing.

### 2. Dispatch Job Later

Schedules a job to run after a specified delay (in seconds).

### 3. Dispatch Job Sync

Dispatches a job to run immediately (synchronously).

## Configuration

### Job Selection

- **Job**: Select from the list of available jobs in your Laravel application
- Only jobs explicitly configured in the `n8n-eloquent.php` config file are available
- To make a job available, add it to the `jobs.available` array in your config:

```php
'jobs' => [
    'available' => [
        'App\\Jobs\\SendEmailJob',
        'App\\Jobs\\ProcessDataJob',
        'App\\Console\\Commands\\CustomCommand',
    ],
],
```

### Job Parameters

- **Parameter Name**: Select from the available parameters for the chosen job
- **Parameter Value**: Enter the value for the selected parameter
- Parameters are automatically discovered from the job's constructor
- All parameter types and requirements are dynamically loaded

### Queue Configuration

- **Queue**: Specify which queue to dispatch the job to (default: 'default')
- **Connection**: Specify the queue connection to use
- **Delay**: Number of seconds to delay job execution (for "Dispatch Job Later" operation)

### Advanced Options

- **After Commit**: Whether to dispatch the job after the database transaction is committed
- **Max Attempts**: Maximum number of attempts for the job
- **Timeout**: Timeout in seconds for the job

## API Endpoints

The node uses the following Laravel API endpoints:

### Get Available Jobs

```
GET /api/n8n/jobs
```

### Get Job Metadata

```
GET /api/n8n/jobs/{job}
```

### Get Job Parameters

```
GET /api/n8n/jobs/{job}/parameters
```

### Dispatch Job

```
POST /api/n8n/jobs/{job}/dispatch
```

## Example Usage

### Basic Job Dispatch

```json
{
  "operation": "dispatch",
  "job": "App\\Jobs\\SendEmailJob",
  "parameters": {
    "email": "user@example.com",
    "subject": "Welcome!",
    "message": "Welcome to our platform!"
  },
  "queue": "emails"
}
```

### Delayed Job Dispatch

```json
{
  "operation": "dispatchLater",
  "job": "App\\Jobs\\ProcessOrderJob",
  "parameters": {
    "orderId": 12345,
    "userId": 67890
  },
  "delay": 300,
  "queue": "orders"
}
```

### Job with Advanced Configuration

```json
{
  "operation": "dispatch",
  "job": "App\\Jobs\\HeavyProcessingJob",
  "parameters": {
    "data": "large-dataset",
    "priority": "high"
  },
  "queue": "processing",
  "connection": "redis",
  "maxAttempts": 3,
  "timeout": 300,
  "afterCommit": true
}
```

## Configuration

### Setting Up Available Jobs

Before you can dispatch jobs through n8n, you need to configure which jobs are available. Edit your `config/n8n-eloquent.php` file and add the jobs you want to make available:

```php
'jobs' => [
    'available' => [
        'App\\Jobs\\SendEmailJob',
        'App\\Jobs\\ProcessDataJob',
        'App\\Console\\Commands\\CustomCommand',
        // Add more job classes here
    ],
],
```

**Important**: Only jobs listed in this array will be discoverable and dispatchable through the n8n API. This provides security by preventing unauthorized access to all jobs in your application.

## Creating Jobs for n8n

To make your jobs compatible with the n8n Job Dispatcher, follow these guidelines:

### 1. Use Constructor Parameters

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $action;
    public $metadata;

    public function __construct(int $userId, string $action, ?array $metadata = null)
    {
        $this->userId = $userId;
        $this->action = $action;
        $this->metadata = $metadata;
    }

    public function handle()
    {
        // Job logic here
    }
}
```

### 2. Use Type Hints

The node automatically detects parameter types from your constructor:

- `string` - Text input
- `int` - Number input
- `bool` - Boolean input
- `array` - JSON input
- `?Type` - Optional parameters

### 3. Handle Metadata

Jobs can receive metadata from n8n workflows:

```php
public function handle()
{
    if ($this->metadata) {
        Log::info('Job dispatched from n8n', [
            'workflow_id' => $this->metadata['workflow_id'] ?? null,
            'node_id' => $this->metadata['node_id'] ?? null,
            'execution_id' => $this->metadata['execution_id'] ?? null,
        ]);
    }
    
    // Job logic here
}
```

## Error Handling

The node provides comprehensive error handling:

- **Job Not Found**: Returns 404 if the specified job class doesn't exist
- **Invalid Parameters**: Returns 500 if required parameters are missing or invalid
- **Queue Errors**: Handles queue connection and dispatch errors gracefully

## Security

- All job dispatches are authenticated using the same HMAC signature system as other n8n endpoints
- Jobs are dispatched with metadata to track their origin
- Queue configuration is validated before dispatch

## Best Practices

1. **Use Descriptive Parameter Names**: Make your job parameters self-documenting
2. **Provide Default Values**: Use optional parameters for non-critical data
3. **Handle Errors Gracefully**: Implement proper error handling in your jobs
4. **Use Appropriate Queues**: Separate different types of jobs into different queues
5. **Monitor Job Performance**: Use Laravel's job monitoring tools to track job execution

## Troubleshooting

### Job Not Appearing in List

- Ensure the job class exists and is autoloaded
- Check that the job implements `ShouldQueue` or extends `Command`
- **Add the job to the `jobs.available` array in your `n8n-eloquent.php` config file**
- Verify the job class can be instantiated

### Parameter Loading Issues

- Ensure the job has a public constructor
- Check that parameter types are properly declared
- Verify the job class can be instantiated

### Dispatch Failures

- Check queue configuration in Laravel
- Verify queue workers are running
- Check job class dependencies and autoloading

## Integration Examples

### User Registration Flow

```json
{
  "operation": "dispatch",
  "job": "App\\Jobs\\SendWelcomeEmailJob",
  "parameters": {
    "userId": "{{ $json.user.id }}",
    "email": "{{ $json.user.email }}"
  },
  "queue": "emails"
}
```

### Order Processing

```json
{
  "operation": "dispatchLater",
  "job": "App\\Jobs\\ProcessOrderJob",
  "parameters": {
    "orderId": "{{ $json.order.id }}",
    "customerId": "{{ $json.order.customer_id }}"
  },
  "delay": 60,
  "queue": "orders"
}
```

### Data Processing

```json
{
  "operation": "dispatch",
  "job": "App\\Jobs\\ProcessDataJob",
  "parameters": {
    "data": "{{ $json.data }}",
    "priority": "high"
  },
  "queue": "processing",
  "maxAttempts": 3,
  "timeout": 600
}
```
