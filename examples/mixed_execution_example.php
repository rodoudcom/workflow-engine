<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\WorkflowEngine\SDK\WorkflowEngine;
use App\WorkflowEngine\SDK\WorkflowBuilder;

// Initialize the workflow engine with mixed execution support
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

echo "=== Mixed Execution Workflow Example ===\n";
echo "Task A (log) -> [Task B (Products) + Task C (Users) + Task D (Blogs)] -> Task E (Comments) -> Task F (End)\n\n";

// Create the mixed execution workflow
$builder = new WorkflowBuilder($engine);

$result = $builder
    ->create('mixed_pipeline', 'Mixed Execution Pipeline')
    
    // Task A: Log (sync, starts first)
    ->addHttpNode('task_a', 'Task A: Log', [
        'url' => 'https://jsonplaceholder.typicode.com/posts/1',
        'method' => 'GET',
        'executionMode' => 'sync',
        'stopWorkflowOnFail' => true,
    ])
    
    // Task B: Fetch Products (async, parallel)
    ->addAsyncHttpNode('task_b', 'Task B: Fetch Products', [
        'url' => 'https://jsonplaceholder.typicode.com/posts',
        'method' => 'GET',
        'stopWorkflowOnFail' => true,
    ])
    
    // Task C: Fetch Users (async, parallel)
    ->addAsyncHttpNode('task_c', 'Task C: Fetch Users', [
        'url' => 'https://jsonplaceholder.typicode.com/users',
        'method' => 'GET',
        'stopWorkflowOnFail' => true,
    ])
    
    // Task D: Fetch Blogs (async, parallel)
    ->addAsyncHttpNode('task_d', 'Task D: Fetch Blogs', [
        'url' => 'https://jsonplaceholder.typicode.com/albums',
        'method' => 'GET',
        'stopWorkflowOnFail' => true,
    ])
    
    // Task E: Get Comments by Product (sync, depends on Task B)
    ->addHttpNode('task_e', 'Task E: Get Comments', [
        'url' => 'https://jsonplaceholder.typicode.com/comments',
        'method' => 'GET',
        'executionMode' => 'sync',
        'stopWorkflowOnFail' => true,
    ])
    
    // Task F: End (sync, depends on C, D, E)
    ->addCodeNode('task_f', 'Task F: End', [
        'code' => '$products = $context["nodes"]["task_b"]["output"] ?? [];
$users = $context["nodes"]["task_c"]["output"] ?? [];
$blogs = $context["nodes"]["task_d"]["output"] ?? [];
$comments = $context["nodes"]["task_e"]["output"] ?? [];

return [
    "summary" => [
        "products_count" => count($products),
        "users_count" => count($users),
        "blogs_count" => count($blogs),
        "comments_count" => count($comments),
    ],
    "execution_time" => date("Y-m-d H:i:s"),
    "status" => "completed"
];',
        'executionMode' => 'sync',
        'stopWorkflowOnFail' => true,
    ])
    
    // Define connections (dependencies)
    ->connect('task_a', 'task_b')  // A -> B
    ->connect('task_a', 'task_c')  // A -> C
    ->connect('task_a', 'task_d')  // A -> D
    ->connect('task_b', 'task_e')  // B -> E
    ->connect('task_c', 'task_f')  // C -> F
    ->connect('task_d', 'task_f')  // D -> F
    ->connect('task_e', 'task_f')  // E -> F
    ->execute();

echo "Workflow Status: " . $result->getStatus() . "\n";
echo "Execution Duration: " . ($result->getDuration() ?? 'N/A') . " seconds\n\n";

if ($result->isCompleted()) {
    $context = $result->getContext();
    
    echo "=== Task Results ===\n";
    
    // Task A result
    if (isset($context['nodes']['task_a']['output'])) {
        $taskA = $context['nodes']['task_a']['output'];
        echo "Task A (Log): " . json_encode(['title' => $taskA['title'] ?? 'N/A']) . "\n";
    }
    
    // Task B result (Products)
    if (isset($context['nodes']['task_b']['output'])) {
        $products = $context['nodes']['task_b']['output'];
        echo "Task B (Products): " . count($products) . " posts fetched\n";
    }
    
    // Task C result (Users)
    if (isset($context['nodes']['task_c']['output'])) {
        $users = $context['nodes']['task_c']['output'];
        echo "Task C (Users): " . count($users) . " users fetched\n";
    }
    
    // Task D result (Blogs)
    if (isset($context['nodes']['task_d']['output'])) {
        $blogs = $context['nodes']['task_d']['output'];
        echo "Task D (Blogs): " . count($blogs) . " albums fetched\n";
    }
    
    // Task E result (Comments)
    if (isset($context['nodes']['task_e']['output'])) {
        $comments = $context['nodes']['task_e']['output'];
        echo "Task E (Comments): " . count($comments) . " comments fetched\n";
    }
    
    // Task F result (Summary)
    if (isset($context['nodes']['task_f']['output'])) {
        $summary = $context['nodes']['task_f']['output']['summary'];
        echo "\n=== Final Summary ===\n";
        echo "Products: " . $summary['products_count'] . "\n";
        echo "Users: " . $summary['users_count'] . "\n";
        echo "Blogs: " . $summary['blogs_count'] . "\n";
        echo "Comments: " . $summary['comments_count'] . "\n";
        echo "Status: " . $summary['status'] . "\n";
        echo "Completed at: " . $context['nodes']['task_f']['output']['execution_time'] . "\n";
    }
    
} elseif ($result->isFailed()) {
    $context = $result->getContext();
    echo "Workflow failed: " . ($context['error'] ?? 'Unknown error') . "\n";
    
    // Show execution logs
    $logs = $result->getLogs();
    echo "\n=== Execution Logs ===\n";
    foreach ($logs as $nodeId => $nodeLogs) {
        echo "Node: {$nodeId}\n";
        foreach ($nodeLogs as $log) {
            echo "  [{$log['level']}] {$log['message']}\n";
        }
    }
}

echo "\n=== Execution Analysis ===\n";

// Get dependency graph information
if (method_exists($builder, 'getWorkflow')) {
    $workflow = $builder->getWorkflow();
    
    // Create dependency graph to analyze execution
    $dependencyGraph = new \App\WorkflowEngine\Execution\DependencyGraph($workflow);
    
    echo "Execution Levels: " . count($dependencyGraph->getExecutionLevels()) . "\n";
    echo "Parallel Groups: " . count($dependencyGraph->getParallelGroups()) . "\n";
    
    $parallelGroups = $dependencyGraph->getParallelGroups();
    foreach ($parallelGroups as $level => $nodes) {
        echo "Level {$level}: " . implode(', ', $nodes) . " (" . count($nodes) . " nodes)\n";
    }
    
    echo "\nNode Execution Modes:\n";
    foreach ($workflow->getNodes() as $node) {
        echo "- {$node->getName()}: {$node->getExecutionMode()}\n";
    }
}

echo "\nMixed Execution Example Complete!\n";