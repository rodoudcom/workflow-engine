<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rodoud\WorkflowEngine\SDK\WorkflowEngine;
use Rodoud\WorkflowEngine\SDK\WorkflowBuilder;
use Rodoud\WorkflowEngine\Node\HttpNode;

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

// Example 1: Create a workflow using the new addJob() method
echo "=== Example 1: Using addJob() method ===\n";

$workflow = $engine->createWorkflow('api_data_fetch', 'API Data Fetch Workflow')
    ->addJob('http', 'fetch_api', 'Fetch API Data', [
        'url' => 'https://jsonplaceholder.typicode.com/users',
        'method' => 'GET',
    ])
    ->addJob('transform', 'transform_data', 'Transform User Data', [
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

// Example 2: Using Workflow Builder with new API
echo "=== Example 2: Workflow Builder with addJob() ===\n";

$builder = new WorkflowBuilder($engine);

$result = $builder
    ->create('data_pipeline', 'Data Processing Pipeline')
    ->addJob('http', 'fetch_data', 'Fetch Data', [
        'url' => 'https://jsonplaceholder.typicode.com/posts',
        'method' => 'GET',
    ])
    ->addJob('transform', 'filter_posts', 'Filter Posts', [
        'operation' => 'filter',
        'condition' => 'isset($item["userId"]) && $item["userId"] <= 3',
    ])
    ->addJob('code', 'count_posts', 'Count Posts', [
        'code' => 'return ["count" => count($input), "data" => $input];',
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

// Example 3: Using job aliases
echo "=== Example 3: Using job aliases ===\n";

$workflow3 = $engine->createWorkflow('alias_example', 'Job Aliases Example')
    ->addJob('api', 'fetch_comments', 'Fetch Comments', [
        'url' => 'https://jsonplaceholder.typicode.com/comments',
        'method' => 'GET',
    ])
    ->addJob('data', 'process_comments', 'Process Comments', [
        'operation' => 'map',
        'mapping' => [
            'id' => 'id',
            'post_id' => 'postId',
            'email' => 'email',
            'body' => 'body',
        ],
    ])
    ->connect('fetch_comments', 'process_comments');

$execution3 = $engine->executeWorkflow($workflow3);

echo "Alias Workflow Status: " . $execution3->getStatus() . "\n";
if ($execution3->isCompleted()) {
    $context = $execution3->getContext();
    $comments = $context['nodes']['process_comments']['output'] ?? [];
    echo "Comments Count: " . count($comments) . "\n";
}

echo "\n";

// Example 4: Using class names
echo "=== Example 4: Using class names ===\n";

$workflow4 = $engine->createWorkflow('class_example', 'Class Names Example')
    ->addJob(HttpNode::class, 'http_request', 'HTTP Request', [
        'url' => 'https://jsonplaceholder.typicode.com/albums',
        'method' => 'GET',
    ])
    ->addJob('transform', 'process_albums', 'Process Albums', [
        'operation' => 'custom',
        'customCode' => 'return ["albums" => array_slice($input, 0, 5)];',
    ])
    ->connect('http_request', 'process_albums');

$execution4 = $engine->executeWorkflow($workflow4);

echo "Class Workflow Status: " . $execution4->getStatus() . "\n";
if ($execution4->isCompleted()) {
    $context = $execution4->getContext();
    $albums = $context['nodes']['process_albums']['output']['albums'] ?? [];
    echo "Albums Count: " . count($albums) . "\n";
}

echo "\n";

// Example 5: Using array configuration
echo "=== Example 5: Using array configuration ===\n";

$workflow5 = $engine->createWorkflow('array_example', 'Array Configuration Example')
    ->addJob([
        'type' => 'http',
        'id' => 'array_http',
        'name' => 'Array HTTP Request',
        'config' => [
            'url' => 'https://jsonplaceholder.typicode.com/todos',
            'method' => 'GET',
        ],
    ])
    ->addJob([
        'type' => 'code',
        'id' => 'array_code',
        'name' => 'Array Code Process',
        'config' => [
            'code' => '$completed = array_filter($input, fn($item) => $item["completed"]);\nreturn ["total" => count($input), "completed" => count($completed)];',
        ],
    ])
    ->connect('array_http', 'array_code');

$execution5 = $engine->executeWorkflow($workflow5);

echo "Array Workflow Status: " . $execution5->getStatus() . "\n";
if ($execution5->isCompleted()) {
    $context = $execution5->getContext();
    $result = $context['nodes']['array_code']['output'] ?? [];
    echo "Total Todos: " . ($result['total'] ?? 0) . "\n";
    echo "Completed Todos: " . ($result['completed'] ?? 0) . "\n";
}

echo "\n";

// Example 6: Get available jobs
echo "=== Example 6: Available Jobs ===\n";

$jobs = $engine->getAvailableJobs();
echo "Available job types:\n";
foreach ($jobs as $jobName => $jobInfo) {
    echo "- {$jobName}: {$jobInfo['description']}\n";
    if (!empty($jobInfo['aliases'])) {
        echo "  Aliases: " . implode(', ', $jobInfo['aliases']) . "\n";
    }
}

echo "\n";

// Example 7: Get execution logs
echo "=== Example 7: Execution Logs ===\n";

$logs = $engine->getLogs();
echo "Total Log Entries: " . count($logs) . "\n";

foreach (array_slice($logs, 0, 5) as $log) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}\n";
}

echo "\nNew Job API Examples Complete!\n";