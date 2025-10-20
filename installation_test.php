<?php

// Simulate user installation and usage
echo "=== Installation Test for rodoudcom/workflow-engine ===\n\n";

// Step 1: Check if composer is available
echo "Step 1: Checking composer installation...\n";
if (shell_exec('which composer') === null && shell_exec('which composer.phar') === null) {
    echo "❌ Composer not found. Please install composer first.\n";
    exit(1);
}
echo "✅ Composer found\n\n";

// Step 2: Check if vendor directory exists
echo "Step 2: Checking vendor directory...\n";
if (!is_dir(__DIR__ . '/vendor')) {
    echo "📦 Installing dependencies...\n";
    $output = shell_exec('cd ' . __DIR__ . ' && composer install 2>&1');
    echo $output . "\n";
} else {
    echo "✅ Vendor directory exists\n";
}

// Step 3: Check autoloader
echo "Step 3: Checking autoloader...\n";
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "❌ Autoloader not found. Run 'composer install' first.\n";
    exit(1);
}
echo "✅ Autoloader found\n\n";

// Step 4: Test namespace loading
echo "Step 4: Testing namespace loading...\n";
require_once __DIR__ . '/vendor/autoload.php';

try {
    // Test the main class
    $engine = new \Rodoud\WorkflowEngine\SDK\WorkflowEngine();
    echo "✅ WorkflowEngine class loaded successfully\n";
    
    // Test workflow creation
    $workflow = $engine->createWorkflow('test', 'Test Workflow');
    echo "✅ Workflow creation works\n";
    
    // Test node types
    $nodeTypes = $engine->getNodeTypes();
    echo "✅ Node types loaded: " . count($nodeTypes) . " types\n";
    
    echo "\n🎉 Installation test passed!\n";
    echo "The library is ready to use.\n\n";
    
    echo "Usage example:\n";
    echo "```php\n";
    echo "<?php\n";
    echo "require_once 'vendor/autoload.php';\n";
    echo "use Rodoud\\WorkflowEngine\\SDK\\WorkflowEngine;\n\n";
    echo "\$engine = new WorkflowEngine();\n";
    echo "\$workflow = \$engine->createWorkflow('my_workflow', 'My Workflow');\n";
    echo "\$execution = \$engine->executeWorkflow(\$workflow);\n";
    echo "```\n";
    
} catch (\Throwable $e) {
    echo "❌ Namespace loading failed: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    // Show debugging info
    echo "\nDebugging information:\n";
    if (file_exists(__DIR__ . '/vendor/composer/autoload_psr4.php')) {
        $psr4 = include __DIR__ . '/vendor/composer/autoload_psr4.php';
        echo "PSR-4 mappings:\n";
        foreach ($psr4 as $namespace => $paths) {
            echo "  '$namespace' -> " . implode(', ', $paths) . "\n";
        }
    }
    
    exit(1);
}