# PHP Workflow Engine SDK

A professional PHP workflow engine SDK similar to n8n, designed for internal projects and automation tasks.

## Features

- **Programmatic & JSON Configuration**: Create workflows programmatically or via JSON configuration
- **Built-in Node Types**: HTTP requests, database operations, data transformations, custom code execution
- **Custom Node Registry**: Create and register custom node types
- **Context Sharing**: Share data and variables between workflow steps
- **Redis Integration**: Real-time execution tracking and history storage
- **Async/Sync Execution**: Support for both synchronous and asynchronous execution modes
- **Comprehensive Logging**: Detailed execution logs with multiple levels
- **Professional SDK**: Well-documented, easy-to-use interface

## Installation

```bash
composer require workflow-engine/php-sdk
```

## Requirements

- PHP 8.1+
- Redis server (optional, for real-time tracking)
- Predis PHP library (automatically installed)
- Extensions: curl, pdo, json
- **Note**: No external async dependencies required - uses pure PHP implementation

## Quick Start

### Basic Usage

```php
<?php

use App\WorkflowEngine\SDK\WorkflowEngine;

// Initialize the engine
$engine = new WorkflowEngine([
    'async' => false,
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
    ],
]);

// Create a workflow
$workflow = $engine->createWorkflow('my_workflow', 'My First Workflow')
    ->addHttpNode('fetch_api', 'Fetch API Data', [
        'url' => 'https://api.example.com/data',
        'method' => 'GET',
    ])
    ->addTransformNode('transform_data', 'Transform Data', [
        'operation' => 'map',
        'mapping' => [
            'id' => 'user_id',
            'name' => 'full_name',
        ],
    ])
    ->connect('fetch_api', 'transform_data');

// Execute the workflow
$execution = $engine->executeWorkflow($workflow);

if ($execution->isCompleted()) {
    $context = $execution->getContext();
    $result = $context['nodes']['transform_data']['output'];
    print_r($result);
}
```

### Using Workflow Builder

```php
use App\WorkflowEngine\SDK\WorkflowBuilder;

$builder = new WorkflowBuilder($engine);

$result = $builder
    ->create('data_pipeline', 'Data Processing Pipeline')
    ->addHttpNode('fetch_data', 'Fetch Data', [
        'url' => 'https://api.example.com/data',
        'method' => 'GET',
    ])
    ->addTransformNode('process_data', 'Process Data', [
        'operation' => 'filter',
        'condition' => '$item["status"] === "active"',
    ])
    ->connect('fetch_data', 'process_data')
    ->execute();
```

### JSON Configuration

```php
$jsonWorkflow = '{
    "id": "json_workflow",
    "name": "JSON Workflow",
    "nodes": [
        {
            "id": "http_request",
            "name": "HTTP Request",
            "type": "http",
            "config": {
                "url": "https://api.example.com/data",
                "method": "GET"
            }
        }
    ],
    "connections": []
}';

$workflow = $engine->loadWorkflowFromJson($jsonWorkflow);
$execution = $engine->executeWorkflow($workflow);
```

## Built-in Node Types

### HTTP Node

Make HTTP requests to external APIs.

```php
$workflow->addHttpNode('api_call', 'API Call', [
    'url' => 'https://api.example.com/users',
    'method' => 'POST',
    'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer {{api_token}}',
    ],
    'body' => [
        'name' => '{{user_name}}',
        'email' => '{{user_email}}',
    ],
    'timeout' => 30,
]);
```

### Database Node

Execute database operations.

```php
$workflow->addDatabaseNode('db_query', 'Database Query', [
    'operation' => 'select',
    'query' => 'SELECT * FROM users WHERE active = 1',
    'params' => [],
    'connection' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'myapp',
        'username' => 'user',
        'password' => 'password',
    ],
]);
```

### Transform Node

Transform and manipulate data.

```php
$workflow->addTransformNode('transform', 'Transform Data', [
    'operation' => 'map',
    'mapping' => [
        'user_id' => 'id',
        'full_name' => 'name',
        'email_address' => 'email',
    ],
]);
```

### Code Node

Execute custom PHP code.

```php
$workflow->addCodeNode('custom_logic', 'Custom Logic', [
    'code' => '$result = array_map(function($item) {
        return ["processed" => true, "data" => $item];
    }, $input);
    return $result;',
    'language' => 'php',
    'timeout' => 30,
]);
```

## Mixed Execution Modes

The workflow engine supports **mixed execution modes** within a single workflow, allowing you to combine synchronous and asynchronous node execution based on your specific requirements.

### Key Concepts:

- **Node-level execution control** - Each node can be sync or async
- **Dependency-based execution** - Nodes execute when their dependencies are satisfied
- **Parallel groups** - Nodes at the same dependency level can execute in parallel
- **Wait/Join mechanisms** - Advanced patterns for coordinating node execution

### Basic Mixed Execution Example:

```php
$builder = new WorkflowBuilder($engine);

$result = $builder
    ->create('mixed_pipeline', 'Mixed Execution Pipeline')
    
    // Task A: Sync execution (starts first)
    ->addHttpNode('task_a', 'Log Start', [
        'url' => 'https://api.example.com/log',
        'executionMode' => 'sync',
    ])
    
    // Tasks B, C, D: Async execution (parallel)
    ->addAsyncHttpNode('task_b', 'Fetch Products', [
        'url' => 'https://api.example.com/products',
    ])
    ->addAsyncHttpNode('task_c', 'Fetch Users', [
        'url' => 'https://api.example.com/users',
    ])
    ->addAsyncHttpNode('task_d', 'Fetch Orders', [
        'url' => 'https://api.example.com/orders',
    ])
    
    // Task E: Sync execution (depends on B)
    ->addHttpNode('task_e', 'Process Products', [
        'url' => 'https://api.example.com/process',
        'executionMode' => 'sync',
    ])
    
    // Define connections
    ->connect('task_a', 'task_b')
    ->connect('task_a', 'task_c')
    ->connect('task_a', 'task_d')
    ->connect('task_b', 'task_e')
    ->connect('task_c', 'task_f')
    ->connect('task_d', 'task_f')
    ->connect('task_e', 'task_f')
    ->execute();
```

### Execution Flow:

```
Task A (sync) 
    ↓
[Task B (async) + Task C (async) + Task D (async)] ← Parallel execution
    ↓                    ↓                    ↓
Task E (sync)         Task F (sync)         Task F (sync)
    ↓                    ↓                    ↓
                    Task G (sync) ← Wait for all dependencies
```

### JSON Configuration with Mixed Execution:

```json
{
  "id": "mixed_workflow",
  "name": "Mixed Execution Workflow",
  "nodes": [
    {
      "id": "sync_task",
      "name": "Synchronous Task",
      "type": "http",
      "config": {
        "url": "https://api.example.com/data",
        "executionMode": "sync"
      }
    },
    {
      "id": "async_task",
      "name": "Asynchronous Task",
      "type": "http",
      "config": {
        "url": "https://api.example.com/data",
        "executionMode": "async"
      }
    }
  ],
  "connections": [
    {
      "from": "sync_task",
      "to": "async_task"
    }
  ]
}
```

### Advanced Wait/Join Patterns:

The workflow engine supports advanced wait and join patterns for complex coordination:

#### Wait for All Dependencies:
```php
// Node waits for ALL dependencies to complete successfully
$node->setExecutionMode('sync');
// Connect to all required dependencies
```

#### Wait for Any Dependency:
```php
// Custom wait condition - proceed when any dependency completes
$waitHandler = new WaitJoinHandler($dependencyGraph);
$waitHandler->createWaitForAny($nodeId, $dependencyNodeIds);
```

#### Conditional Wait:
```php
// Wait based on data conditions
$waitHandler->createConditionalWait($nodeId, $dependencyNodeIds, function ($results) {
    return count($results['products']) > 10 && count($results['users']) > 5;
});
```

#### Data-Based Wait:
```php
// Wait based on expression evaluation
$waitHandler->createDataBasedWait($nodeId, $dependencyNodeIds, '$results["products"]["count"] >= 5');
```

### Execution Analysis:

The workflow engine provides detailed execution analysis:

```php
// Get dependency graph information
$dependencyGraph = new DependencyGraph($workflow);

echo "Execution Levels: " . count($dependencyGraph->getExecutionLevels()) . "\n";
echo "Parallel Groups: " . count($dependencyGraph->getParallelGroups()) . "\n";
echo "Critical Path: " . implode(' -> ', $dependencyGraph->getCriticalPath()) . "\n";

// Show execution levels
$parallelGroups = $dependencyGraph->getParallelGroups();
foreach ($parallelGroups as $level => $nodes) {
    echo "Level {$level}: " . implode(', ', $nodes) . "\n";
}
```

### Performance Benefits:

Mixed execution provides significant performance benefits:

1. **Parallel Processing** - Independent nodes execute simultaneously
2. **Resource Optimization** - Sync nodes for critical path, async for independent tasks
3. **Dependency Management** - Automatic handling of complex dependencies
4. **Scalability** - Efficient use of available workers

### Configuration Options:

```php
$engine = new WorkflowEngine([
    'async' => true,           // Enable async execution
    'max_workers' => 4,        // Number of parallel workers
    'log_level' => 'info',     // Log level
    'redis' => [               // Redis configuration using Predis
        'scheme' => 'tcp',     // Connection scheme (tcp, unix, tls)
        'host' => '127.0.0.1', // Redis host
        'port' => 6379,        // Redis port
        'password' => null,    // Redis password (if required)
        'database' => 0,       // Redis database number
        'timeout' => 5.0,      // Connection timeout in seconds
        'read_write_timeout' => 5.0, // Read/write timeout in seconds
        'prefix' => 'workflow:', // Optional key prefix
    ],
]);
```

### Redis Configuration Options:

The workflow engine uses Predis for Redis connectivity, supporting various configurations:

#### Basic TCP Connection:
```php
'redis' => [
    'host' => '127.0.0.1',
    'port' => 6379,
]
```

#### With Authentication:
```php
'redis' => [
    'host' => '127.0.0.1',
    'port' => 6379,
    'password' => 'your-password',
    'database' => 1,
]
```

#### Unix Socket Connection:
```php
'redis' => [
    'scheme' => 'unix',
    'path' => '/var/run/redis/redis.sock',
]
```

#### TLS/SSL Connection:
```php
'redis' => [
    'scheme' => 'tls',
    'host' => 'your-redis-host.com',
    'port' => 6380,
    'password' => 'your-password',
    'timeout' => 10.0,
]
```

#### Cluster Configuration:
```php
'redis' => [
    'cluster' => 'redis',
    'parameters' => [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
    ],
    'options' => [
        'cluster_read_timeout' => 5,
        'cluster_node_selector' => 'Predis\Cluster\RedisStrategy',
    ],
]
```

### Without Redis:
If you don't need Redis features (real-time tracking, history), simply omit the redis configuration:

```php
$engine = new WorkflowEngine([
    'async' => true,
    'max_workers' => 4,
    'log_level' => 'info',
    // No redis configuration needed
]);
```

### Best Practices:

1. **Use Async for I/O Operations** - HTTP requests, database queries
2. **Use Sync for Critical Path** - Data processing, validation
3. **Minimize Dependencies** - Reduce unnecessary wait conditions
4. **Monitor Performance** - Use execution analysis to optimize
5. **Handle Failures** - Configure `stopWorkflowOnFail` appropriately

### Error Handling in Mixed Execution:

```php
// Configure error handling per node
$node->setStopWorkflowOnFail(false); // Continue on error

// Check execution results
if ($execution->isFailed()) {
    $context = $execution->getContext();
    $error = $context['error'] ?? 'Unknown error';
    
    // Get detailed logs
    $logs = $execution->getLogs();
    foreach ($logs as $nodeId => $nodeLogs) {
        echo "Node {$nodeId} logs:\n";
        foreach ($nodeLogs as $log) {
            echo "  [{$log['level']}] {$log['message']}\n";
        }
    }
}
```

## Context and Variables

Workflows share context between nodes. Use variables in configuration:

```php
$workflow->addHttpNode('api_call', 'API Call', [
    'url' => 'https://api.example.com/users/{{user_id}}',
    'headers' => [
        'Authorization' => 'Bearer {{auth_token}}',
    ],
]);
```

Access node outputs in subsequent nodes:

```php
$workflow->addTransformNode('process', 'Process Data', [
    'operation' => 'custom',
    'customCode' => '$userData = $context["nodes"]["api_call"]["output"]; return $userData;',
]);
```

## Async Execution

Enable async execution for parallel processing:

```php
$engine = new WorkflowEngine([
    'async' => true,
    'max_workers' => 4,
]);

$execution = $engine->executeWorkflowAsync($workflow, $context);
```

## Logging and Monitoring

Get execution logs:

```php
$logs = $engine->getLogs();
$executionLogs = $engine->getLogsByExecution($executionId);

// Export logs
$jsonLogs = $engine->exportLogs('json');
$csvLogs = $engine->exportLogs('csv');
```

Monitor running executions:

```php
$runningExecutions = $engine->getRunningExecutions();
$execution = $engine->getExecution($executionId);
$history = $engine->getWorkflowHistory($workflowId);
```

## Custom Node Types

Create custom node types:

```php
use App\WorkflowEngine\Core\AbstractNode;

class CustomNode extends AbstractNode
{
    protected string $type = 'custom';
    
    public function execute(array $context, array $input = []): array
    {
        // Custom logic here
        return [
            'success' => true,
            'data' => $result,
        ];
    }
    
    public function getDescription(): string
    {
        return 'Custom node description';
    }
    
    public function getCategory(): string
    {
        return 'Custom';
    }
    
    public function getIcon(): string
    {
        return 'custom-icon';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'input1' => ['type' => 'string'],
                'input2' => ['type' => 'number'],
            ],
        ];
    }
    
    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'result' => ['type' => 'string'],
            ],
        ];
    }
}

// Register the custom node
$engine->registerNodeType('custom', CustomNode::class);
```

## Configuration Options

```php
$engine = new WorkflowEngine([
    'async' => false,           // Enable async execution
    'max_workers' => 4,         // Number of parallel workers
    'log_level' => 'info',      // Log level: debug, info, warning, error, critical
    'redis' => [                // Redis configuration
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
    ],
]);
```

## Error Handling

Each node can be configured to stop the workflow on failure:

```php
$workflow->addHttpNode('api_call', 'API Call', [
    'url' => 'https://api.example.com/data',
    'stopWorkflowOnFail' => false,  // Continue on error
]);
```

Check execution status:

```php
$execution = $engine->executeWorkflow($workflow);

if ($execution->isFailed()) {
    $context = $execution->getContext();
    $error = $context['error'] ?? 'Unknown error';
    echo "Workflow failed: {$error}";
}
```

## Examples

See the `examples/` directory for complete usage examples:

- `basic_usage.php` - Basic workflow creation and execution
- `async_example.php` - Async execution examples
- `workflows/` - Sample JSON workflow configurations

## API Reference

### WorkflowEngine

Main SDK class for workflow management.

#### Methods

- `createWorkflow($id, $name, $description = '')` - Create a new workflow
- `loadWorkflowFromJson($json)` - Load workflow from JSON string
- `loadWorkflowFromFile($filePath)` - Load workflow from JSON file
- `executeWorkflow($workflow, $context = [])` - Execute a workflow
- `executeWorkflowAsync($workflow, $context = [])` - Execute workflow asynchronously
- `registerNodeType($type, $className)` - Register a custom node type
- `getNodeTypes()` - Get available node types
- `getExecution($executionId)` - Get execution by ID
- `getRunningExecutions()` - Get all running executions
- `getWorkflowHistory($workflowId)` - Get workflow execution history
- `cancelExecution($executionId)` - Cancel a running execution
- `getLogs()` - Get all logs
- `exportLogs($format = 'json')` - Export logs in specified format

### WorkflowBuilder

Fluent interface for building workflows.

#### Methods

- `create($id, $name, $description = '')` - Create new workflow
- `loadFromJson($json)` - Load from JSON
- `loadFromFile($filePath)` - Load from file
- `addHttpNode($id, $name, $config = [])` - Add HTTP node
- `addDatabaseNode($id, $name, $config = [])` - Add database node
- `addTransformNode($id, $name, $config = [])` - Add transform node
- `addCodeNode($id, $name, $config = [])` - Add code node
- `addNode($type, $id, $name, $config = [])` - Add custom node
- `connect($from, $to, $fromOutput = 'output', $toInput = 'input')` - Connect nodes
- `execute($context = [])` - Execute workflow
- `executeAsync($context = [])` - Execute workflow asynchronously
- `save($filePath)` - Save workflow to file
- `validate()` - Validate workflow
- `toJson()` - Export to JSON
- `toArray()` - Export to array

## License

MIT License - see LICENSE file for details.