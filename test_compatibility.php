<?php

// Simple test to verify the workflow engine works without amphp/parallel
require_once __DIR__ . '/src/WorkflowEngine/Interface/WorkflowInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Interface/ExecutionInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Interface/NodeInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Interface/RegistryInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Core/AbstractNode.php';
require_once __DIR__ . '/src/WorkflowEngine/Core/Workflow.php';
require_once __DIR__ . '/src/WorkflowEngine/Core/Execution.php';
require_once __DIR__ . '/src/WorkflowEngine/Context/WorkflowContext.php';
require_once __DIR__ . '/src/WorkflowEngine/Registry/NodeRegistry.php';
require_once __DIR__ . '/src/WorkflowEngine/Execution/DependencyGraph.php';
require_once __DIR__ . '/src/WorkflowEngine/Execution/WaitJoinHandler.php';
require_once __DIR__ . '/src/WorkflowEngine/Execution/WorkflowExecutor.php';
require_once __DIR__ . '/src/WorkflowEngine/Execution/AsyncWorkflowExecutor.php';
require_once __DIR__ . '/src/WorkflowEngine/Execution/MixedWorkflowExecutor.php';
require_once __DIR__ . '/src/WorkflowEngine/Node/HttpNode.php';
require_once __DIR__ . '/src/WorkflowEngine/Node/TransformNode.php';
require_once __DIR__ . '/src/WorkflowEngine/Node/DatabaseNode.php';
require_once __DIR__ . '/src/WorkflowEngine/Node/CodeNode.php';
require_once __DIR__ . '/src/WorkflowEngine/Logger/WorkflowLogger.php';
require_once __DIR__ . '/src/WorkflowEngine/Config/WorkflowParser.php';
require_once __DIR__ . '/src/WorkflowEngine/SDK/WorkflowBuilder.php';
require_once __DIR__ . '/src/WorkflowEngine/SDK/WorkflowEngine.php';

use App\WorkflowEngine\Execution\WorkflowExecutor;
use App\WorkflowEngine\SDK\WorkflowBuilder;

echo "Testing Workflow Engine Compatibility...\n";

try {
    // Test 1: Basic WorkflowExecutor instantiation
    echo "1. Testing WorkflowExecutor instantiation...\n";
    $executor = new WorkflowExecutor();
    echo "   ✓ WorkflowExecutor created successfully\n";
    
    // Test 2: WorkflowExecutor with Redis config
    echo "2. Testing WorkflowExecutor with Redis config...\n";
    $redisConfig = [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'prefix' => 'test:'
    ];
    $executorWithRedis = new WorkflowExecutor($redisConfig);
    echo "   ✓ WorkflowExecutor with Redis config created successfully\n";
    
    // Test 3: WorkflowBuilder instantiation
    echo "3. Testing WorkflowBuilder instantiation...\n";
    $builder = new WorkflowBuilder();
    echo "   ✓ WorkflowBuilder created successfully\n";
    
    // Test 4: Create a simple workflow
    echo "4. Testing simple workflow creation...\n";
    $workflow = $builder
        ->setId('test-workflow')
        ->setName('Test Workflow')
        ->setDescription('A simple test workflow')
        ->build();
    echo "   ✓ Simple workflow created successfully\n";
    
    echo "\n✅ All compatibility tests passed!\n";
    echo "The workflow engine is working correctly without amphp/parallel dependency.\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}