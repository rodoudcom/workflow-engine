<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\WorkflowEngine\SDK\WorkflowEngine;

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

echo "=== Async Workflow Execution Example ===\n";

// Create multiple workflows for parallel execution
$workflows = [];
$contexts = [];

for ($i = 1; $i <= 3; $i++) {
    $workflow = $engine->createWorkflow("async_workflow_{$i}", "Async Workflow {$i}")
        ->addHttpNode("fetch_data_{$i}", "Fetch Data {$i}", [
            'url' => "https://jsonplaceholder.typicode.com/users/{$i}",
            'method' => 'GET',
        ])
        ->addTransformNode("transform_{$i}", "Transform Data {$i}", [
            'operation' => 'map',
            'mapping' => [
                'user_id' => 'id',
                'user_name' => 'name',
                'user_email' => 'email',
            ],
        ])
        ->connect("fetch_data_{$i}", "transform_{$i}");
    
    $workflows[] = $workflow;
    $contexts[] = ["workflow_index" => $i];
}

echo "Executing " . count($workflows) . " workflows in parallel...\n";

// Execute workflows asynchronously
try {
    $promises = [];
    foreach ($workflows as $index => $workflow) {
        $promises[] = $engine->executeWorkflowAsync($workflow, $contexts[$index]);
    }
    
    // Wait for all executions to complete
    $results = \Amp\Promise\wait(\Amp\Promise\all($promises));
    
    echo "All async executions completed!\n";
    
    foreach ($results as $index => $execution) {
        echo "Workflow {$index} Status: " . $execution->getStatus() . "\n";
        if ($execution->isCompleted()) {
            $context = $execution->getContext();
            $output = $context['nodes']["transform_{$index + 1}"]['output'] ?? [];
            echo "  Result: " . json_encode($output) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Async execution failed: " . $e->getMessage() . "\n";
}

echo "\nAsync Example Complete!\n";