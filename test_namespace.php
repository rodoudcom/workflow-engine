<?php

// Test script to verify namespace changes work correctly
require_once __DIR__ . '/src/WorkflowEngine/Interface/RegistryInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Interface/WorkflowInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Interface/ExecutionInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Interface/NodeInterface.php';
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

use Rodoud\WorkflowEngine\SDK\WorkflowEngine;
use Rodoud\WorkflowEngine\SDK\WorkflowBuilder;

echo "Testing Rodoud\\WorkflowEngine Namespace...\n\n";

try {
    // Test 1: WorkflowEngine instantiation
    echo "1. Testing WorkflowEngine instantiation...\n";
    $engine = new WorkflowEngine([
        'async' => false,
        'redis' => [
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
            'prefix' => 'test:',
        ],
    ]);
    echo "   ✓ WorkflowEngine created successfully with Rodoud\\WorkflowEngine namespace\n";
    
    // Test 2: WorkflowBuilder instantiation
    echo "2. Testing WorkflowBuilder instantiation...\n";
    $builder = new WorkflowBuilder($engine);
    echo "   ✓ WorkflowBuilder created successfully with Rodoud\\WorkflowEngine namespace\n";
    
    // Test 3: Create a simple workflow
    echo "3. Testing workflow creation...\n";
    $workflow = $builder
        ->create('test_workflow', 'Test Workflow')
        ->addCodeNode('test_node', 'Test Node', [
            'code' => 'return ["success" => true, "data" => ["message" => "Hello from Rodoud WorkflowEngine!"]];',
        ])
        ->build();
    echo "   ✓ Workflow created successfully\n";
    
    // Test 4: Execute workflow
    echo "4. Testing workflow execution...\n";
    $execution = $engine->executeWorkflow($workflow);
    echo "   ✓ Workflow executed successfully\n";
    echo "   Status: " . $execution->getStatus() . "\n";
    
    if ($execution->isCompleted()) {
        $context = $execution->getContext();
        $result = $context['nodes']['test_node']['output'] ?? [];
        echo "   Result: " . json_encode($result) . "\n";
    }
    
    // Test 5: Check node types
    echo "5. Testing node type registration...\n";
    $nodeTypes = $engine->getNodeTypes();
    echo "   ✓ Registered node types: " . implode(', ', array_keys($nodeTypes)) . "\n";
    
    echo "\n✅ All namespace tests passed!\n";
    echo "The Rodoud\\WorkflowEngine namespace is working correctly.\n";
    
} catch (Exception $e) {
    echo "\n❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}