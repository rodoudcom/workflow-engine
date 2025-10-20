<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rodoud\WorkflowEngine\SDK\WorkflowEngine;
use Rodoud\WorkflowEngine\SDK\WorkflowBuilder;

// Initialize the workflow engine with async support
$engine = new WorkflowEngine([
    'async' => true,
    'max_workers' => 4,
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

echo "=== Async Workflow Execution Example (New API) ===\n";

// Example 1: Basic async jobs with addJob
echo "\n=== Example 1: Basic async jobs ===\n";

$workflow1 = $engine->createWorkflow('async_basic', 'Basic Async Workflow')
    ->addAsyncJob('http', 'fetch_users', 'Fetch Users', [
        'url' => 'https://jsonplaceholder.typicode.com/users/1',
        'method' => 'GET',
    ])
    ->addAsyncJob('http', 'fetch_posts', 'Fetch Posts', [
        'url' => 'https://jsonplaceholder.typicode.com/posts/1',
        'method' => 'GET',
    ])
    ->addJob('code', 'process_results', 'Process Results', [
        'code' => '$users = $context["nodes"]["fetch_users"]["output"] ?? [];\n$posts = $context["nodes"]["fetch_posts"]["output"] ?? [];\nreturn [\n    "user_name" => $users["name"] ?? "Unknown",\n    "post_title" => $posts["title"] ?? "Unknown",\n    "processed_at" => date("Y-m-d H:i:s")\n];',
    ])
    ->connect('fetch_users', 'process_results')
    ->connect('fetch_posts', 'process_results');

$execution1 = $engine->executeWorkflow($workflow1);

echo "Workflow Status: " . $execution1->getStatus() . "\n";
echo "Execution Duration: " . ($execution1->getDuration() ?? 'N/A') . " seconds\n";

if ($execution1->isCompleted()) {
    $context = $execution1->getContext();
    $result = $context['nodes']['process_results']['output'] ?? [];
    echo "Result: " . json_encode($result) . "\n";
}

// Example 2: Mixed sync and async jobs
echo "\n=== Example 2: Mixed sync and async jobs ===\n";

$workflow2 = $engine->createWorkflow('mixed_workflow', 'Mixed Sync/Async Workflow')
    ->addJob('code', 'sync_start', 'Sync Start', [
        'code' => 'return ["timestamp" => time(), "message" => "Starting workflow"];',
    ])
    ->addAsyncJob('http', 'async_data1', 'Async Data 1', [
        'url' => 'https://jsonplaceholder.typicode.com/comments/1',
        'method' => 'GET',
    ])
    ->addAsyncJob('http', 'async_data2', 'Async Data 2', [
        'url' => 'https://jsonplaceholder.typicode.com/albums/1',
        'method' => 'GET',
    ])
    ->addAsyncJob('http', 'async_data3', 'Async Data 3', [
        'url' => 'https://jsonplaceholder.typicode.com/todos/1',
        'method' => 'GET',
    ])
    ->addJob('code', 'sync_end', 'Sync End', [
        'code' => '$start = $context["nodes"]["sync_start"]["output"] ?? [];\n$comment = $context["nodes"]["async_data1"]["output"] ?? [];\n$album = $context["nodes"]["async_data2"]["output"] ?? [];\n$todo = $context["nodes"]["async_data3"]["output"] ?? [];\nreturn [\n    "start_time" => $start["timestamp"],\n    "comment_email" => $comment["email"] ?? "",\n    "album_title" => $album["title"] ?? "",\n    "todo_title" => $todo["title"] ?? "",\n    "total_async_tasks" => 3,\n    "completed_at" => time()\n];',
    ])
    ->connect('sync_start', 'async_data1')
    ->connect('sync_start', 'async_data2')
    ->connect('sync_start', 'async_data3')
    ->connect('async_data1', 'sync_end')
    ->connect('async_data2', 'sync_end')
    ->connect('async_data3', 'sync_end');

$execution2 = $engine->executeWorkflow($workflow2);

echo "Mixed Workflow Status: " . $execution2->getStatus() . "\n";
echo "Execution Duration: " . ($execution2->getDuration() ?? 'N/A') . " seconds\n";

if ($execution2->isCompleted()) {
    $context = $execution2->getContext();
    $result = $context['nodes']['sync_end']['output'] ?? [];
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}

// Example 3: WorkflowBuilder with async jobs
echo "\n=== Example 3: WorkflowBuilder with async jobs ===\n";

$builder = new WorkflowBuilder($engine);

$result3 = $builder
    ->create('builder_async', 'Builder Async Workflow')
    ->addJob('code', 'builder_start', 'Builder Start', [
        'code' => 'return ["workflow_id" => "builder_async", "started" => true];',
    ])
    ->addAsyncJob('http', 'builder_async1', 'Builder Async 1', [
        'url' => 'https://jsonplaceholder.typicode.com/users',
        'method' => 'GET',
    ])
    ->addAsyncJob('http', 'builder_async2', 'Builder Async 2', [
        'url' => 'https://jsonplaceholder.typicode.com/posts',
        'method' => 'GET',
    ])
    ->addJob('transform', 'builder_process', 'Builder Process', [
        'operation' => 'custom',
        'customCode' => '$users = $context["nodes"]["builder_async1"]["output"] ?? [];\n$posts = $context["nodes"]["builder_async2"]["output"] ?? [];\nreturn [\n    "users_count" => count($users),\n    "posts_count" => count($posts),\n    "total_items" => count($users) + count($posts)\n];',
    ])
    ->connect('builder_start', 'builder_async1')
    ->connect('builder_start', 'builder_async2')
    ->connect('builder_async1', 'builder_process')
    ->connect('builder_async2', 'builder_process')
    ->execute();

echo "Builder Async Status: " . $result3->getStatus() . "\n";
echo "Execution Duration: " . ($result3->getDuration() ?? 'N/A') . " seconds\n";

if ($result3->isCompleted()) {
    $context = $result3->getContext();
    $result = $context['nodes']['builder_process']['output'] ?? [];
    echo "Result: " . json_encode($result) . "\n";
}

// Example 4: Error handling in async jobs
echo "\n=== Example 4: Error handling in async jobs ===\n";

$workflow4 = $engine->createWorkflow('error_handling', 'Error Handling Workflow')
    ->addJob('code', 'error_start', 'Error Start', [
        'code' => 'return ["test" => "error_handling"];',
    ])
    ->addAsyncJob('http', 'error_async', 'Error Async', [
        'url' => 'https://invalid-url-that-will-fail.com/data',
        'method' => 'GET',
        'timeout' => 5,
        'stopWorkflowOnFail' => false,
    ])
    ->addJob('code', 'error_recovery', 'Error Recovery', [
        'code' => '$asyncResult = $context["nodes"]["error_async"]["output"] ?? [];\n$success = $context["nodes"]["error_async"]["success"] ?? false;\nreturn [\n    "async_success" => $success,\n    "error_message" => $asyncResult["error"] ?? "No error",\n    "recovery_successful" => true\n];',
    ])
    ->connect('error_start', 'error_async')
    ->connect('error_async', 'error_recovery');

$execution4 = $engine->executeWorkflow($workflow4);

echo "Error Handling Status: " . $execution4->getStatus() . "\n";

if ($execution4->isCompleted()) {
    $context = $execution4->getContext();
    $result = $context['nodes']['error_recovery']['output'] ?? [];
    echo "Result: " . json_encode($result) . "\n";
} elseif ($execution4->isFailed()) {
    echo "Workflow failed as expected due to async error\n";
    $context = $execution4->getContext();
    echo "Error: " . ($context['error'] ?? 'Unknown error') . "\n";
}

// Example 5: Performance comparison
echo "\n=== Example 5: Performance comparison ===\n";

// Sync workflow
$syncStart = microtime(true);
$syncWorkflow = $engine->createWorkflow('sync_perf', 'Sync Performance')
    ->addJob('http', 'sync1', 'Sync 1', [
        'url' => 'https://jsonplaceholder.typicode.com/users/1',
        'method' => 'GET',
    ])
    ->addJob('http', 'sync2', 'Sync 2', [
        'url' => 'https://jsonplaceholder.typicode.com/posts/1',
        'method' => 'GET',
    ])
    ->addJob('http', 'sync3', 'Sync 3', [
        'url' => 'https://jsonplaceholder.typicode.com/comments/1',
        'method' => 'GET',
    ])
    ->addJob('code', 'sync_result', 'Sync Result', [
        'code' => 'return ["execution_mode" => "sync", "completed" => true];',
    ])
    ->connect('sync1', 'sync_result')
    ->connect('sync2', 'sync_result')
    ->connect('sync3', 'sync_result');

$syncExecution = $engine->executeWorkflow($syncWorkflow);
$syncDuration = microtime(true) - $syncStart;

// Async workflow
$asyncStart = microtime(true);
$asyncWorkflow = $engine->createWorkflow('async_perf', 'Async Performance')
    ->addAsyncJob('http', 'async1', 'Async 1', [
        'url' => 'https://jsonplaceholder.typicode.com/users/1',
        'method' => 'GET',
    ])
    ->addAsyncJob('http', 'async2', 'Async 2', [
        'url' => 'https://jsonplaceholder.typicode.com/posts/1',
        'method' => 'GET',
    ])
    ->addAsyncJob('http', 'async3', 'Async 3', [
        'url' => 'https://jsonplaceholder.typicode.com/comments/1',
        'method' => 'GET',
    ])
    ->addJob('code', 'async_result', 'Async Result', [
        'code' => 'return ["execution_mode" => "async", "completed" => true];',
    ])
    ->connect('async1', 'async_result')
    ->connect('async2', 'async_result')
    ->connect('async3', 'async_result');

$asyncExecution = $engine->executeWorkflow($asyncWorkflow);
$asyncDuration = microtime(true) - $asyncStart;

echo "Sync Execution Time: " . number_format($syncDuration, 3) . " seconds\n";
echo "Async Execution Time: " . number_format($asyncDuration, 3) . " seconds\n";
echo "Performance Improvement: " . number_format(($syncDuration - $asyncDuration) / $syncDuration * 100, 1) . "%\n";

echo "\n=== Async Examples Complete! ===\n";