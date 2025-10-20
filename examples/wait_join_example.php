<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\WorkflowEngine\SDK\WorkflowEngine;
use App\WorkflowEngine\Execution\WaitJoinHandler;

// Initialize the workflow engine
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

echo "=== Wait/Join Mechanisms Example ===\n";
echo "Demonstrating advanced wait conditions and join patterns\n\n";

// Create a workflow with complex wait conditions
$workflow = $engine->createWorkflow('wait_join_example', 'Wait/Join Example')
    ->addHttpNode('data_source_a', 'Data Source A', [
        'url' => 'https://jsonplaceholder.typicode.com/users',
        'method' => 'GET',
        'executionMode' => 'async',
    ])
    ->addHttpNode('data_source_b', 'Data Source B', [
        'url' => 'https://jsonplaceholder.typicode.com/posts',
        'method' => 'GET',
        'executionMode' => 'async',
    ])
    ->addHttpNode('data_source_c', 'Data Source C', [
        'url' => 'https://jsonplaceholder.typicode.com/comments',
        'method' => 'GET',
        'executionMode' => 'async',
    ])
    ->addCodeNode('wait_for_all', 'Wait for All Sources', [
        'code' => '$users = $context["nodes"]["data_source_a"]["output"] ?? [];
$posts = $context["nodes"]["data_source_b"]["output"] ?? [];
$comments = $context["nodes"]["data_source_c"]["output"] ?? [];

return [
    "all_data_received" => true,
    "users_count" => count($users),
    "posts_count" => count($posts),
    "comments_count" => count($comments),
    "total_records" => count($users) + count($posts) + count($comments)
];',
        'executionMode' => 'sync',
    ])
    ->addCodeNode('wait_for_any', 'Wait for Any Source', [
        'code' => '$users = $context["nodes"]["data_source_a"]["output"] ?? [];
$posts = $context["nodes"]["data_source_b"]["output"] ?? [];
$comments = $context["nodes"]["data_source_c"]["output"] ?? [];

$first_available = [];
if (!empty($users)) $first_available["users"] = count($users);
if (!empty($posts)) $first_available["posts"] = count($posts);
if (!empty($comments)) $first_available["comments"] = count($comments);

return [
    "first_data_available" => true,
    "data" => $first_available
];',
        'executionMode' => 'sync',
    ])
    ->addCodeNode('conditional_wait', 'Conditional Wait', [
        'code' => '$users = $context["nodes"]["data_source_a"]["output"] ?? [];
$posts = $context["nodes"]["data_source_b"]["output"] ?? [];

// Only proceed if we have at least 5 users AND 10 posts
$condition_met = count($users) >= 5 && count($posts) >= 10;

return [
    "condition_met" => $condition_met,
    "users_count" => count($users),
    "posts_count" => count($posts),
    "message" => $condition_met ? "Condition satisfied: sufficient data" : "Condition not met: insufficient data"
];',
        'executionMode' => 'sync',
    ])
    ->addCodeNode('final_aggregator', 'Final Aggregator', [
        'code' => '$waitForAll = $context["nodes"]["wait_for_all"]["output"] ?? [];
$waitForAny = $context["nodes"]["wait_for_any"]["output"] ?? [];
$conditionalWait = $context["nodes"]["conditional_wait"]["output"] ?? [];

return [
    "workflow_completed" => true,
    "summary" => [
        "all_sources" => $waitForAll,
        "first_source" => $waitForAny,
        "conditional_result" => $conditionalWait
    ],
    "completion_time" => date("Y-m-d H:i:s")
];',
        'executionMode' => 'sync',
    ]);

// Define connections
$workflow
    ->connect('data_source_a', 'wait_for_all')
    ->connect('data_source_b', 'wait_for_all')
    ->connect('data_source_c', 'wait_for_all')
    
    ->connect('data_source_a', 'wait_for_any')
    ->connect('data_source_b', 'wait_for_any')
    ->connect('data_source_c', 'wait_for_any')
    
    ->connect('data_source_a', 'conditional_wait')
    ->connect('data_source_b', 'conditional_wait')
    
    ->connect('wait_for_all', 'final_aggregator')
    ->connect('wait_for_any', 'final_aggregator')
    ->connect('conditional_wait', 'final_aggregator');

echo "Executing workflow with wait/join mechanisms...\n";

// Execute the workflow
$execution = $engine->executeWorkflow($workflow);

echo "Workflow Status: " . $execution->getStatus() . "\n";
echo "Execution Duration: " . ($execution->getDuration() ?? 'N/A') . " seconds\n\n";

if ($execution->isCompleted()) {
    $context = $execution->getContext();
    
    echo "=== Wait/Join Results ===\n";
    
    // Wait for All Results
    if (isset($context['nodes']['wait_for_all']['output'])) {
        $waitForAll = $context['nodes']['wait_for_all']['output'];
        echo "Wait for All Results:\n";
        echo "  Users: " . $waitForAll['users_count'] . "\n";
        echo "  Posts: " . $waitForAll['posts_count'] . "\n";
        echo "  Comments: " . $waitForAll['comments_count'] . "\n";
        echo "  Total Records: " . $waitForAll['total_records'] . "\n\n";
    }
    
    // Wait for Any Results
    if (isset($context['nodes']['wait_for_any']['output'])) {
        $waitForAny = $context['nodes']['wait_for_any']['output'];
        echo "Wait for Any Results:\n";
        echo "  First Available Data: " . json_encode($waitForAny['data']) . "\n\n";
    }
    
    // Conditional Wait Results
    if (isset($context['nodes']['conditional_wait']['output'])) {
        $conditionalWait = $context['nodes']['conditional_wait']['output'];
        echo "Conditional Wait Results:\n";
        echo "  Condition Met: " . ($conditionalWait['condition_met'] ? 'YES' : 'NO') . "\n";
        echo "  Users Count: " . $conditionalWait['users_count'] . "\n";
        echo "  Posts Count: " . $conditionalWait['posts_count'] . "\n";
        echo "  Message: " . $conditionalWait['message'] . "\n\n";
    }
    
    // Final Aggregator
    if (isset($context['nodes']['final_aggregator']['output'])) {
        $final = $context['nodes']['final_aggregator']['output'];
        echo "=== Final Summary ===\n";
        echo "Workflow completed at: " . $final['completion_time'] . "\n";
        echo "Summary: " . json_encode($final['summary'], JSON_PRETTY_PRINT) . "\n";
    }
    
} elseif ($execution->isFailed()) {
    $context = $execution->getContext();
    echo "Workflow failed: " . ($context['error'] ?? 'Unknown error') . "\n";
}

echo "\n=== Dependency Analysis ===\n";

// Analyze the dependency graph
$dependencyGraph = new \App\WorkflowEngine\Execution\DependencyGraph($workflow);

echo "Start Nodes: " . implode(', ', $dependencyGraph->getStartNodes()) . "\n";
echo "End Nodes: " . implode(', ', $dependencyGraph->getEndNodes()) . "\n";
echo "Critical Path: " . implode(' -> ', $dependencyGraph->getCriticalPath()) . "\n";

echo "\nExecution Levels:\n";
$parallelGroups = $dependencyGraph->getParallelGroups();
foreach ($parallelGroups as $level => $nodes) {
    $syncNodes = [];
    $asyncNodes = [];
    
    foreach ($nodes as $nodeId) {
        $node = $workflow->getNode($nodeId);
        if ($node->getExecutionMode() === 'async') {
            $asyncNodes[] = $nodeId;
        } else {
            $syncNodes[] = $nodeId;
        }
    }
    
    echo "  Level {$level}: ";
    if (!empty($syncNodes)) {
        echo "SYNC[" . implode(', ', $syncNodes) . "] ";
    }
    if (!empty($asyncNodes)) {
        echo "ASYNC[" . implode(', ', $asyncNodes) . "]";
    }
    echo "\n";
}

echo "\n=== Wait/Join Example Complete! ===\n";