# Autoloader Fix Guide

## Problem
When installing the library, users get:  
`Fatal error: Uncaught Error: Class "Rodoud\WorkflowEngine\WorkflowEngine" not found`

## Root Cause
The autoloader configuration in `composer.json` was incorrect:
- **Before**: `"Rodoud\\WorkflowEngine\\": "src/"` 
- **After**: `"Rodoud\\": "src/"`

## Why This Works
With the directory structure:
```
src/
└── WorkflowEngine/
    ├── SDK/
    │   ├── WorkflowEngine.php
    │   └── WorkflowBuilder.php
    └── ...
```

The autoloader needs to map:
- `Rodoud\` → `src/`
- So `Rodoud\WorkflowEngine\SDK\WorkflowEngine` → `src/WorkflowEngine/SDK/WorkflowEngine.php`

## Fixed Configuration
```json
{
    "autoload": {
        "psr-4": {
            "Rodoud\\": "src/"
        }
    }
}
```

## Installation Instructions

### For Users Installing the Library

1. **Install the package**:
   ```bash
   composer require rodoudcom/workflow-engine
   ```

2. **Use in your code**:
   ```php
   <?php
   require_once 'vendor/autoload.php';
   
   use Rodoud\WorkflowEngine\SDK\WorkflowEngine;
   
   $engine = new WorkflowEngine();
   $workflow = $engine->createWorkflow('my_workflow', 'My Workflow');
   $execution = $engine->executeWorkflow($workflow);
   ```

### For Development

1. **Regenerate autoloader**:
   ```bash
   composer dump-autoload
   ```

2. **Test the fix**:
   ```bash
   php test_autoloader.php
   php installation_test.php
   ```

## Verification

The fix has been tested with:
- ✅ Class loading works correctly
- ✅ Namespace resolution works
- ✅ All functionality preserved
- ✅ No breaking changes to API

## Files Changed

1. **composer.json** - Updated autoloader configuration
2. **test_autoloader.php** - New test file
3. **installation_test.php** - New installation test

## Troubleshooting

If you still get the error:

1. **Run composer install**:
   ```bash
   composer install --no-dev
   ```

2. **Clear composer cache**:
   ```bash
   composer clear-cache
   composer install
   ```

3. **Check autoloader file**:
   ```php
   <?php
   $psr4 = include 'vendor/composer/autoload_psr4.php';
   print_r($psr4);
   ```

4. **Verify file exists**:
   ```bash
   ls -la vendor/rodoudcom/workflow-engine/src/WorkflowEngine/SDK/WorkflowEngine.php
   ```

## Summary

The autoloader issue has been fixed by correctly mapping the `Rodoud\` namespace to the `src/` directory. Users can now install and use the library without any namespace loading errors.