<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rodoud\WorkflowEngine\SDK\WorkflowEngine;
use Rodoud\WorkflowEngine\SDK\WorkflowBuilder;

// Initialize the workflow engine
$engine = new WorkflowEngine([
    'async' => false,
    'redis' => [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
        'timeout' => 5.0,
    ],
    'log_level' => 'info',
]);

// Example 1: Create a workflow programmatically
echo "=== Example 1: Programmatic Workflow ===\n";

$workflow = $engine->createWorkflow('api_data_fetch', 'API Data Fetch Workflow')
    ->addHttpNode('fetch_api', 'Fetch API Data', [
        'url' => 'https://jsonplaceholder.typicode.com/users',
        'method' => 'GET',
    ])
    ->addTransformNode('transform_data', 'Transform User Data', [
        'operation' => 'map',
        'mapping' => [
            'id' => 'id',
            'name' => 'name',
            'email' => 'email',
            'company' => 'company.name',
        ],
    ])
    ->connect('fetch_api', 'transform_data');

// Execute the workflow
$execution = $engine->executeWorkflow($workflow);

echo "Workflow Status: " . $execution->getStatus() . "\n";
echo "Execution Duration: " . ($execution->getDuration() ?? 'N/A') . " seconds\n";

if ($execution->isCompleted()) {
    $context = $execution->getContext();
    $output = $context['nodes']['transform_data']['output'] ?? [];
    echo "Transformed Users Count: " . count($output) . "\n";
    echo "First User: " . json_encode($output[0] ?? []) . "\n";
}

echo "\n";

// Example 2: Using Workflow Builder
echo "=== Example 2: Workflow Builder ===\n";

$builder = new WorkflowBuilder($engine);

$result = $builder
    ->create('data_pipeline', 'Data Processing Pipeline')
    ->addHttpNode('fetch_data', 'Fetch Data', [
        'url' => 'https://jsonplaceholder.typicode.com/posts',
        'method' => 'GET',
    ])
    ->addTransformNode('filter_posts', 'Filter Posts', [
        'operation' => 'filter',
        'condition' => 'isset($item["userId"]) && $item["userId"] <= 3',
    ])
    ->addTransformNode('count_posts', 'Count Posts', [
        'operation' => 'custom',
        'customCode' => 'return ["count" => count($input), "data" => $input];',
    ])
    ->connect('fetch_data', 'filter_posts')
    ->connect('filter_posts', 'count_posts')
    ->execute();

echo "Pipeline Status: " . $result->getStatus() . "\n";
if ($result->isCompleted()) {
    $context = $result->getContext();
    $count = $context['nodes']['count_posts']['output']['count'] ?? 0;
    echo "Filtered Posts Count: {$count}\n";
}

echo "\n";

// Example 3: Load workflow from JSON
echo "=== Example 3: JSON Workflow ===\n";

$jsonWorkflow = [
    'id' => 'json_example',
    'name' => 'JSON Example Workflow',
    'description' => 'Workflow loaded from JSON configuration',
    'nodes' => [
        [
            'id' => 'http_request',
            'name' => 'HTTP Request',
            'type' => 'http',
            'config' => [
                'url' => 'https://jsonplaceholder.typicode.com/comments',
                'method' => 'GET',
                'stopWorkflowOnFail' => true,
            ],
        ],
        [
            'id' => 'data_transform',
            'name' => 'Transform Comments',
            'type' => 'transform',
            'config' => [
                'operation' => 'map',
                'mapping' => [
                    'id' => 'id',
                    'post_id' => 'postId',
                    'email' => 'email',
                    'body' => 'body',
                ],
                'stopWorkflowOnFail' => true,
            ],
        ],
    ],
    'connections' => [
        [
            'from' => 'http_request',
            'to' => 'data_transform',
        ],
    ],
];

$workflowFromJson = $engine->loadWorkflowFromJson(json_encode($jsonWorkflow));
$execution = $engine->executeWorkflow($workflowFromJson);

echo "JSON Workflow Status: " . $execution->getStatus() . "\n";
if ($execution->isCompleted()) {
    $context = $execution->getContext();
    $comments = $context['nodes']['data_transform']['output'] ?? [];
    echo "Comments Count: " . count($comments) . "\n";
}

echo "\n";

// Example 4: Get execution logs
echo "=== Example 4: Execution Logs ===\n";

$logs = $engine->getLogs();
echo "Total Log Entries: " . count($logs) . "\n";

foreach (array_slice($logs, 0, 5) as $log) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
}

echo "\n";

// Example 5: Get available node types
echo "=== Example 5: Available Node Types ===\n";

$nodeTypes = $engine->getNodeTypes();
foreach ($nodeTypes as $type => $info) {
    echo "- {$type}: {$info['description']} (Category: {$info['category']})\n";
}

echo "\nWorkflow Engine Examples Complete!\n";