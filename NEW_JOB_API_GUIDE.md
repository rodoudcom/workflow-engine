# New Job API Guide

## Overview

The Workflow Engine now features a flexible and powerful `addJob()` method that supports multiple approaches to adding jobs to workflows. This new API replaces the previous node-specific methods and provides a more intuitive and extensible way to build workflows.

## Available Job Types

### Built-in Jobs

| Job Type | Aliases | Description | Category |
|----------|---------|-------------|----------|
| `http` | `httpRequest`, `api` | Make HTTP requests to external APIs | Communication |
| `database` | `db`, `sql` | Execute database operations | Data |
| `transform` | `data`, `map` | Transform and manipulate data | Data |
| `code` | `script`, `php` | Execute custom PHP code | Logic |

## API Methods

### Core Methods

#### `addJob($jobTypeOrClassOrInstance, $id = null, $name = null, $config = [])`

The main method for adding jobs to workflows. Supports multiple input types:

**Parameters:**
- `$jobTypeOrClassOrInstance` - Job type string, class name, or instance
- `$id` - Job identifier (optional for instances)
- `$name` - Human-readable name (optional for instances)
- `$config` - Job configuration array (optional for instances)

**Returns:** `self` for method chaining

#### `addAsyncJob($jobTypeOrClassOrInstance, $id = null, $name = null, $config = [])`

Same as `addJob()` but sets execution mode to `async`.

## Usage Examples

### 1. Using Job Type String

```php
$workflow = $engine->createWorkflow('my_workflow', 'My Workflow')
    ->addJob('http', 'fetch_api', 'Fetch API Data', [
        'url' => 'https://api.example.com/data',
        'method' => 'GET',
    ])
    ->addJob('transform', 'process_data', 'Process Data', [
        'operation' => 'map',
        'mapping' => ['id' => 'user_id', 'name' => 'full_name'],
    ])
    ->connect('fetch_api', 'process_data');
```

### 2. Using Job Aliases

```php
$workflow = $engine->createWorkflow('alias_workflow', 'Alias Workflow')
    ->addJob('api', 'fetch_users', 'Fetch Users', [
        'url' => 'https://api.example.com/users',
        'method' => 'GET',
    ])
    ->addJob('php', 'process_users', 'Process Users', [
        'code' => 'return array_map(fn($user) => ["name" => $user["name"]], $input);',
    ])
    ->connect('fetch_users', 'process_users');
```

### 3. Using Class Names

```php
use Rodoud\WorkflowEngine\Node\HttpNode;
use Rodoud\WorkflowEngine\Node\CodeNode;

$workflow = $engine->createWorkflow('class_workflow', 'Class Workflow')
    ->addJob(HttpNode::class, 'http_request', 'HTTP Request', [
        'url' => 'https://api.example.com/data',
        'method' => 'POST',
    ])
    ->addJob(CodeNode::class, 'process_data', 'Process Data', [
        'code' => 'return ["processed" => true, "data" => $input];',
    ])
    ->connect('http_request', 'process_data');
```

### 4. Using Node Instances

```php
use Rodoud\WorkflowEngine\Node\HttpNode;

$httpNode = new HttpNode([
    'id' => 'api_call',
    'name' => 'API Call',
    'url' => 'https://api.example.com/data',
    'method' => 'GET',
]);

$workflow = $engine->createWorkflow('instance_workflow', 'Instance Workflow')
    ->addJob($httpNode)
    ->addJob('code', 'process', 'Process', [
        'code' => 'return ["result" => $input];',
    ])
    ->connect('api_call', 'process');
```

### 5. Using Array Configuration

```php
$workflow = $engine->createWorkflow('array_workflow', 'Array Workflow')
    ->addJob([
        'type' => 'http',
        'id' => 'fetch_data',
        'name' => 'Fetch Data',
        'config' => [
            'url' => 'https://api.example.com/data',
            'method' => 'GET',
        ],
    ])
    ->addJob([
        'type' => 'transform',
        'id' => 'transform_data',
        'name' => 'Transform Data',
        'config' => [
            'operation' => 'filter',
            'condition' => '$item["active"] === true',
        ],
    ])
    ->connect('fetch_data', 'transform_data');
```

### 6. Async Jobs

```php
$workflow = $engine->createWorkflow('async_workflow', 'Async Workflow')
    ->addJob('code', 'start', 'Start', [
        'code' => 'return ["timestamp" => time()];',
    ])
    ->addAsyncJob('http', 'fetch_data', 'Fetch Data', [
        'url' => 'https://api.example.com/data',
        'method' => 'GET',
    ])
    ->addAsyncJob('http', 'fetch_more', 'Fetch More', [
        'url' => 'https://api.example.com/more',
        'method' => 'GET',
    ])
    ->addJob('code', 'end', 'End', [
        'code' => 'return ["completed" => true];',
    ])
    ->connect('start', 'fetch_data')
    ->connect('start', 'fetch_more')
    ->connect('fetch_data', 'end')
    ->connect('fetch_more', 'end');
```

## WorkflowBuilder Integration

The new API is fully integrated with WorkflowBuilder:

```php
use Rodoud\WorkflowEngine\SDK\WorkflowBuilder;

$builder = new WorkflowBuilder($engine);

$result = $builder
    ->create('pipeline', 'Data Pipeline')
    ->addJob('http', 'fetch', 'Fetch Data', [
        'url' => 'https://api.example.com/data',
        'method' => 'GET',
    ])
    ->addJob('transform', 'process', 'Process Data', [
        'operation' => 'map',
        'mapping' => ['id' => 'user_id'],
    ])
    ->connect('fetch', 'process')
    ->execute();
```

## Custom Job Registration

### Register Custom Jobs

```php
// Register a custom job type
$engine->registerJob('email', CustomEmailJob::class);

// Register a job class with auto-detection
$engine->registerJobClass(CustomDataProcessor::class);

// Use the custom job
$workflow = $engine->createWorkflow('custom_workflow', 'Custom Workflow')
    ->addJob('email', 'send_notification', 'Send Notification', [
        'to' => 'user@example.com',
        'subject' => 'Workflow Completed',
        'message' => 'Your workflow has completed successfully.',
    ]);
```

### Create Custom Jobs

```php
<?php

namespace App\Jobs;

use Rodoud\WorkflowEngine\Core\AbstractNode;

/**
 * @Job(name="email", description="Send email notifications")
 * @Job(name="notification", description="Send notifications")
 */
class CustomEmailJob extends AbstractNode
{
    protected string $type = 'email';
    
    public function execute(array $context, array $input = []): array
    {
        $to = $this->config['to'] ?? '';
        $subject = $this->config['subject'] ?? '';
        $message = $this->config['message'] ?? '';
        
        // Your email sending logic here
        $success = $this->sendEmail($to, $subject, $message);
        
        return [
            'success' => $success,
            'data' => [
                'to' => $to,
                'subject' => $subject,
                'sent_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }
    
    public function getDescription(): string
    {
        return 'Send email notifications';
    }
    
    public function getCategory(): string
    {
        return 'Communication';
    }
    
    private function sendEmail(string $to, string $subject, string $message): bool
    {
        // Implement your email sending logic
        return true;
    }
}
```

## Job Discovery

### Get Available Jobs

```php
// Get all available jobs with descriptions
$jobs = $engine->getAvailableJobs();

foreach ($jobs as $jobName => $jobInfo) {
    echo "- {$jobName}: {$jobInfo['description']}\n";
    echo "  Category: {$jobInfo['category']}\n";
    echo "  Aliases: " . implode(', ', $jobInfo['aliases']) . "\n";
}
```

### Get Jobs by Category

```php
$jobRegistry = $engine->getJobRegistry();
$communicationJobs = $jobRegistry->getJobsByCategory('Communication');

foreach ($communicationJobs as $jobName => $jobInfo) {
    echo "- {$jobName}: {$jobInfo['description']}\n";
}
```

## Error Handling

### Invalid Job Type

```php
try {
    $workflow->addJob('invalid_type', 'node', 'Invalid Node');
} catch (InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage();
    // Output: Job type 'invalid_type' not found. Available types: http, database, transform, code
}
```

### Invalid Class

```php
try {
    $workflow->addJob('NonExistentClass', 'node', 'Invalid Class');
} catch (ClassNotFoundException $e) {
    echo "Error: " . $e->getMessage();
    // Output: Class 'NonExistentClass' not found
}
```

## Migration from Old API

### Before (Old API)

```php
$workflow = $engine->createWorkflow('my_workflow', 'My Workflow')
    ->addHttpNode('fetch_api', 'Fetch API Data', [
        'url' => 'https://api.example.com/data',
        'method' => 'GET',
    ])
    ->addTransformNode('transform_data', 'Transform Data', [
        'operation' => 'map',
        'mapping' => ['id' => 'user_id'],
    ])
    ->connect('fetch_api', 'transform_data');
```

### After (New API)

```php
$workflow = $engine->createWorkflow('my_workflow', 'My Workflow')
    ->addJob('http', 'fetch_api', 'Fetch API Data', [
        'url' => 'https://api.example.com/data',
        'method' => 'GET',
    ])
    ->addJob('transform', 'transform_data', 'Transform Data', [
        'operation' => 'map',
        'mapping' => ['id' => 'user_id'],
    ])
    ->connect('fetch_api', 'transform_data');
```

## Best Practices

1. **Use descriptive job IDs** - Make them meaningful and unique
2. **Leverage aliases** - Use aliases for more readable code
3. **Configure error handling** - Set `stopWorkflowOnFail` appropriately
4. **Use async for I/O operations** - Improve performance with async jobs
5. **Group related jobs** - Use consistent naming conventions
6. **Document custom jobs** - Use annotations for auto-discovery

## Performance Considerations

- **Async jobs** run in parallel when possible
- **Mixed execution** allows optimal performance
- **Job instances** are slightly faster than type strings
- **Array configuration** has minimal overhead

## Testing

Run the test suite to verify the new API:

```bash
php test_new_job_api.php
```

Run the examples to see the API in action:

```bash
php examples/new_job_api_examples.php
php examples/basic_usage_new_api.php
php examples/async_example_new_api.php
```

## Summary

The new Job API provides:

- ✅ **Flexibility** - Multiple ways to add jobs
- ✅ **Intuitiveness** - Simple and consistent interface
- ✅ **Extensibility** - Easy custom job registration
- ✅ **Auto-discovery** - Annotation-based job mapping
- ✅ **Performance** - Optimized execution
- ✅ **Backward compatibility** - Old methods still work

The new `addJob()` method is now the recommended way to build workflows, providing a clean and powerful API for all use cases.