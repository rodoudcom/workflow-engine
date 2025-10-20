<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\WorkflowEngine\SDK\WorkflowEngine;

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

echo "=== Loading Workflow from JSON File ===\n";

try {
    // Load workflow from JSON file
    $workflow = $engine->loadWorkflowFromFile(__DIR__ . '/workflows/api_data_pipeline.json');
    
    echo "Loaded workflow: {$workflow->getName()}\n";
    echo "Description: {$workflow->getDescription()}\n";
    echo "Node count: " . count($workflow->getNodes()) . "\n";
    echo "Connection count: " . count($workflow->getConnections()) . "\n";
    
    // Validate the workflow
    if ($engine->validateWorkflow($workflow)) {
        echo "Workflow validation: PASSED\n";
    } else {
        echo "Workflow validation: FAILED\n";
        exit(1);
    }
    
    echo "\nExecuting workflow...\n";
    
    // Execute the workflow
    $execution = $engine->executeWorkflow($workflow);
    
    echo "Execution Status: " . $execution->getStatus() . "\n";
    echo "Execution Duration: " . ($execution->getDuration() ?? 'N/A') . " seconds\n";
    
    if ($execution->isCompleted()) {
        $context = $execution->getContext();
        
        // Display results from each node
        foreach ($workflow->getNodes() as $node) {
            $nodeId = $node->getId();
            if (isset($context['nodes'][$nodeId]['output'])) {
                echo "\n--- {$node->getName()} Output ---\n";
                $output = $context['nodes'][$nodeId]['output'];
                
                if (is_array($output)) {
                    if (isset($output['merged_data'])) {
                        echo "Merged Users: " . count($output['merged_data']) . "\n";
                        echo "Total Users: " . ($output['total_users'] ?? 0) . "\n";
                        echo "Total Posts: " . ($output['total_posts'] ?? 0) . "\n";
                        
                        // Show first user with posts
                        if (!empty($output['merged_data'])) {
                            $firstUser = $output['merged_data'][0];
                            echo "First User: {$firstUser['user']['name']} ({$firstUser['post_count']} posts)\n";
                        }
                    } else {
                        echo "Record count: " . count($output) . "\n";
                        if (!empty($output)) {
                            echo "First record: " . json_encode(array_slice($output, 0, 1)[0]) . "\n";
                        }
                    }
                } else {
                    echo "Output: " . json_encode($output) . "\n";
                }
            }
        }
    } elseif ($execution->isFailed()) {
        $context = $execution->getContext();
        echo "Execution failed: " . ($context['error'] ?? 'Unknown error') . "\n";
        
        // Show node logs for debugging
        $logs = $execution->getLogs();
        echo "\n--- Execution Logs ---\n";
        foreach ($logs as $nodeId => $nodeLogs) {
            echo "Node: {$nodeId}\n";
            foreach ($nodeLogs as $log) {
                echo "  [{$log['level']}] {$log['message']}\n";
                if (!empty($log['data'])) {
                    echo "    Data: " . json_encode($log['data']) . "\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\nJSON Workflow Example Complete!\n";