<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\WorkflowEngine\SDK\WorkflowEngine;

echo "=== Testing Redis Connection with Predis ===\n\n";

// Test configurations
$configurations = [
    'Basic TCP Connection' => [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
    ],
    'With Timeout' => [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 5.0,
        'read_write_timeout' => 5.0,
    ],
    'With Database' => [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 1,
    ],
    'With Prefix' => [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'prefix' => 'test:',
    ],
];

foreach ($configurations as $name => $redisConfig) {
    echo "Testing: {$name}\n";
    echo str_repeat('-', 40) . "\n";
    
    try {
        $engine = new WorkflowEngine([
            'async' => false,
            'redis' => $redisConfig,
            'log_level' => 'info',
        ]);
        
        // Create a simple test workflow
        $workflow = $engine->createWorkflow('test_workflow', 'Test Workflow')
            ->addCodeNode('test_node', 'Test Node', [
                'code' => 'return ["message" => "Hello from Redis test!", "timestamp" => date("Y-m-d H:i:s")];',
                'executionMode' => 'sync',
            ]);
        
        // Execute the workflow
        $execution = $engine->executeWorkflow($workflow);
        
        echo "✅ Connection successful!\n";
        echo "   Status: " . $execution->getStatus() . "\n";
        echo "   Duration: " . ($execution->getDuration() ?? 'N/A') . " seconds\n";
        
        if ($execution->isCompleted()) {
            $context = $execution->getContext();
            $result = $context['nodes']['test_node']['output'] ?? [];
            echo "   Result: " . json_encode($result) . "\n";
        }
        
        // Test Redis-specific features
        $executionId = $execution->getId();
        $retrievedExecution = $engine->getExecution($executionId);
        
        if ($retrievedExecution) {
            echo "✅ Redis storage/retrieval working!\n";
        } else {
            echo "❌ Redis storage/retrieval failed!\n";
        }
        
        // Test history
        $history = $engine->getWorkflowHistory('test_workflow');
        echo "📊 History entries: " . count($history) . "\n";
        
    } catch (\Exception $e) {
        echo "❌ Connection failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "=== Testing Without Redis ===\n";

try {
    $engine = new WorkflowEngine([
        'async' => false,
        'log_level' => 'info',
        // No Redis configuration
    ]);
    
    $workflow = $engine->createWorkflow('no_redis_test', 'No Redis Test')
        ->addCodeNode('test_node', 'Test Node', [
            'code' => 'return ["message" => "Working without Redis!", "timestamp" => date("Y-m-d H:i:s")];',
            'executionMode' => 'sync',
        ]);
    
    $execution = $engine->executeWorkflow($workflow);
    
    echo "✅ Working without Redis!\n";
    echo "   Status: " . $execution->getStatus() . "\n";
    echo "   Duration: " . ($execution->getDuration() ?? 'N/A') . " seconds\n";
    
    if ($execution->isCompleted()) {
        $context = $execution->getContext();
        $result = $context['nodes']['test_node']['output'] ?? [];
        echo "   Result: " . json_encode($result) . "\n";
    }
    
    // Test that Redis-specific features return empty/null
    $retrievedExecution = $engine->getExecution($execution->getId());
    echo "📊 Retrieved execution (should be null): " . ($retrievedExecution ? 'Found' : 'Null') . "\n";
    
    $history = $engine->getWorkflowHistory('no_redis_test');
    echo "📊 History entries (should be 0): " . count($history) . "\n";
    
} catch (\Exception $e) {
    echo "❌ Failed without Redis: " . $e->getMessage() . "\n";
}

echo "\n=== Redis Connection Test Complete ===\n";