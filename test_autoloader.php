<?php

// Test the autoloader configuration
require_once __DIR__ . '/vendor/autoload.php';

echo "Testing autoloader for Rodoud\\WorkflowEngine namespace...\n\n";

try {
    // Test 1: Try to load the main WorkflowEngine class
    echo "1. Testing WorkflowEngine class loading...\n";
    $engine = new \Rodoud\WorkflowEngine\SDK\WorkflowEngine();
    echo "   ✓ WorkflowEngine class loaded successfully\n";
    
    // Test 2: Try to load WorkflowBuilder
    echo "2. Testing WorkflowBuilder class loading...\n";
    $builder = new \Rodoud\WorkflowEngine\SDK\WorkflowBuilder($engine);
    echo "   ✓ WorkflowBuilder class loaded successfully\n";
    
    // Test 3: Try to create a workflow
    echo "3. Testing workflow creation...\n";
    $workflow = $engine->createWorkflow('test', 'Test Workflow');
    echo "   ✓ Workflow created successfully\n";
    
    // Test 4: Check node types
    echo "4. Testing node type registration...\n";
    $nodeTypes = $engine->getNodeTypes();
    echo "   ✓ Node types loaded: " . implode(', ', array_keys($nodeTypes)) . "\n";
    
    echo "\n✅ Autoloader is working correctly!\n";
    echo "The Rodoud\\WorkflowEngine namespace is properly configured.\n";
    
} catch (\Throwable $e) {
    echo "\n❌ Autoloader test failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if ($e instanceof \Error) {
        echo "Type: Error\n";
    } elseif ($e instanceof \Exception) {
        echo "Type: Exception\n";
    }
    
    echo "\nDebugging information:\n";
    echo "- Current working directory: " . getcwd() . "\n";
    echo "- Composer autoloader exists: " . (file_exists(__DIR__ . '/vendor/autoload.php') ? 'Yes' : 'No') . "\n";
    
    if (file_exists(__DIR__ . '/vendor/composer/autoload_psr4.php')) {
        $psr4 = include __DIR__ . '/vendor/composer/autoload_psr4.php';
        echo "- PSR-4 mappings:\n";
        foreach ($psr4 as $namespace => $paths) {
            echo "  $namespace -> " . implode(', ', $paths) . "\n";
        }
    }
    
    exit(1);
}