<?php

// Comprehensive test for the new Job API
require_once __DIR__ . '/src/WorkflowEngine/Interface/RegistryInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Interface/WorkflowInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Interface/ExecutionInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Interface/NodeInterface.php';
require_once __DIR__ . '/src/WorkflowEngine/Core/AbstractNode.php';
require_once __DIR__ . '/src/WorkflowEngine/Core/Workflow.php';
require_once __DIR__ . '/src/WorkflowEngine/Core/Execution.php';
require_once __DIR__ . '/src/WorkflowEngine/Context/WorkflowContext.php';
require_once __DIR__ . '/src/WorkflowEngine/Registry/NodeRegistry.php';
require_once __DIR__ . '/src/WorkflowEngine/Registry/JobRegistry.php';
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
use Rodoud\WorkflowEngine\Node\HttpNode;
use Rodoud\WorkflowEngine\Node\CodeNode;

echo "=== Testing New Job API ===\n\n";

$testsPassed = 0;
$totalTests = 0;

function runTest(string $testName, callable $test): void {
    global $testsPassed, $totalTests;
    $totalTests++;
    
    echo "Test {$totalTests}: {$testName}... ";
    
    try {
        $result = $test();
        if ($result) {
            echo "✅ PASSED\n";
            $testsPassed++;
        } else {
            echo "❌ FAILED\n";
        }
    } catch (Exception $e) {
        echo "❌ FAILED - " . $e->getMessage() . "\n";
    }
}

// Initialize engine
$engine = new WorkflowEngine();

// Test 1: Basic addJob with job type string
runTest("Basic addJob with job type string", function() use ($engine) {
    $workflow = $engine->createWorkflow('test1', 'Test 1');
    $workflow->addJob('http', 'http_node', 'HTTP Node', [
        'url' => 'https://jsonplaceholder.typicode.com/users/1',
        'method' => 'GET',
    ]);
    
    $node = $workflow->getNode('http_node');
    return $node !== null && $node->getType() === 'http';
});

// Test 2: addJob with job alias
runTest("addJob with job alias", function() use ($engine) {
    $workflow = $engine->createWorkflow('test2', 'Test 2');
    $workflow->addJob('api', 'api_node', 'API Node', [
        'url' => 'https://jsonplaceholder.typicode.com/posts/1',
        'method' => 'GET',
    ]);
    
    $node = $workflow->getNode('api_node');
    return $node !== null && $node->getType() === 'http';
});

// Test 3: addJob with class name
runTest("addJob with class name", function() use ($engine) {
    $workflow = $engine->createWorkflow('test3', 'Test 3');
    $workflow->addJob(HttpNode::class, 'class_node', 'Class Node', [
        'url' => 'https://jsonplaceholder.typicode.com/comments/1',
        'method' => 'GET',
    ]);
    
    $node = $workflow->getNode('class_node');
    return $node !== null && $node instanceof HttpNode;
});

// Test 4: addJob with node instance
runTest("addJob with node instance", function() use ($engine) {
    $workflow = $engine->createWorkflow('test4', 'Test 4');
    $node = new HttpNode([
        'id' => 'instance_node',
        'name' => 'Instance Node',
        'url' => 'https://jsonplaceholder.typicode.com/albums/1',
        'method' => 'GET',
    ]);
    $workflow->addJob($node);
    
    $retrievedNode = $workflow->getNode('instance_node');
    return $retrievedNode !== null && $retrievedNode === $node;
});

// Test 5: addJob with array configuration
runTest("addJob with array configuration", function() use ($engine) {
    $workflow = $engine->createWorkflow('test5', 'Test 5');
    $workflow->addJob([
        'type' => 'http',
        'id' => 'array_node',
        'name' => 'Array Node',
        'config' => [
            'url' => 'https://jsonplaceholder.typicode.com/todos/1',
            'method' => 'GET',
        ],
    ]);
    
    $node = $workflow->getNode('array_node');
    return $node !== null && $node->getType() === 'http';
});

// Test 6: addAsyncJob
runTest("addAsyncJob", function() use ($engine) {
    $workflow = $engine->createWorkflow('test6', 'Test 6');
    $workflow->addAsyncJob('http', 'async_node', 'Async Node', [
        'url' => 'https://jsonplaceholder.typicode.com/users/1',
        'method' => 'GET',
    ]);
    
    $node = $workflow->getNode('async_node');
    return $node !== null && $node->getExecutionMode() === 'async';
});

// Test 7: WorkflowBuilder addJob
runTest("WorkflowBuilder addJob", function() use ($engine) {
    $builder = new WorkflowBuilder($engine);
    $workflow = $builder
        ->create('test7', 'Test 7')
        ->addJob('http', 'builder_node', 'Builder Node', [
            'url' => 'https://jsonplaceholder.typicode.com/photos/1',
            'method' => 'GET',
        ])
        ->getWorkflow();
    
    $node = $workflow->getNode('builder_node');
    return $node !== null && $node->getType() === 'http';
});

// Test 8: Workflow execution with addJob
runTest("Workflow execution with addJob", function() use ($engine) {
    $workflow = $engine->createWorkflow('test8', 'Test 8');
    $workflow->addJob('code', 'code_node', 'Code Node', [
        'code' => 'return ["success" => true, "data" => ["message" => "Hello World"]];',
    ]);
    
    $execution = $engine->executeWorkflow($workflow);
    return $execution->isCompleted();
});

// Test 9: Job registry functionality
runTest("Job registry functionality", function() use ($engine) {
    $jobs = $engine->getAvailableJobs();
    return isset($jobs['http']) && isset($jobs['code']) && isset($jobs['transform']);
});

// Test 10: Job aliases
runTest("Job aliases", function() use ($engine) {
    $jobs = $engine->getAvailableJobs();
    return isset($jobs['api']) && isset($jobs['php']) && isset($jobs['data']);
});

// Test 11: Custom job registration
runTest("Custom job registration", function() use ($engine) {
    $engine->registerJob('custom', CodeNode::class);
    $workflow = $engine->createWorkflow('test11', 'Test 11');
    $workflow->addJob('custom', 'custom_node', 'Custom Node', [
        'code' => 'return ["custom" => true];',
    ]);
    
    $node = $workflow->getNode('custom_node');
    return $node !== null && $node instanceof CodeNode;
});

// Test 12: Error handling - invalid job type
runTest("Error handling - invalid job type", function() use ($engine) {
    try {
        $workflow = $engine->createWorkflow('test12', 'Test 12');
        $workflow->addJob('invalid_type', 'invalid_node', 'Invalid Node');
        return false; // Should not reach here
    } catch (InvalidArgumentException $e) {
        return true; // Expected exception
    }
});

// Test 13: Error handling - invalid class
runTest("Error handling - invalid class", function() use ($engine) {
    try {
        $workflow = $engine->createWorkflow('test13', 'Test 13');
        $workflow->addJob('NonExistentClass', 'invalid_class_node', 'Invalid Class Node');
        return false; // Should not reach here
    } catch (ClassNotFoundException $e) {
        return true; // Expected exception
    }
});

// Test 14: Complex workflow with multiple job types
runTest("Complex workflow with multiple job types", function() use ($engine) {
    $workflow = $engine->createWorkflow('test14', 'Test 14');
    $workflow
        ->addJob('code', 'start_node', 'Start Node', [
            'code' => 'return ["data" => ["message" => "Start"]];',
        ])
        ->addJob('transform', 'middle_node', 'Middle Node', [
            'operation' => 'custom',
            'customCode' => 'return ["processed" => true, "original" => $input];',
        ])
        ->addJob('code', 'end_node', 'End Node', [
            'code' => 'return ["final" => true, "data" => $input];',
        ])
        ->connect('start_node', 'middle_node')
        ->connect('middle_node', 'end_node');
    
    $execution = $engine->executeWorkflow($workflow);
    return $execution->isCompleted() && count($workflow->getNodes()) === 3;
});

// Test 15: Get available jobs from workflow
runTest("Get available jobs from workflow", function() use ($engine) {
    $workflow = $engine->createWorkflow('test15', 'Test 15');
    $jobs = $workflow->getAvailableJobs();
    return is_array($jobs) && count($jobs) > 0;
});

echo "\n=== Test Results ===\n";
echo "Tests Passed: {$testsPassed}/{$totalTests}\n";

if ($testsPassed === $totalTests) {
    echo "🎉 All tests passed! The new Job API is working correctly.\n";
    exit(0);
} else {
    echo "❌ Some tests failed. Please check the implementation.\n";
    exit(1);
}