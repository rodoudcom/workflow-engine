<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Rodoud\WorkflowEngine\SDK\WorkflowEngine;
use Rodoud\WorkflowEngine\SDK\WorkflowBuilder;
use Rodoud\WorkflowEngine\Node\HttpNode;
use Rodoud\WorkflowEngine\Node\CodeNode;

echo "=== New Job API Examples ===\n\n";

// Initialize the workflow engine
$engine = new WorkflowEngine([
    'async' => false,
    'log_level' => 'info',
]);

// Show available jobs
echo "Available Jobs:\n";
$jobs = $engine->getAvailableJobs();
foreach ($jobs as $jobName => $jobInfo) {
    echo "- {$jobName}: {$jobInfo['description']} (Category: {$jobInfo['category']})\n";
    if (!empty($jobInfo['aliases'])) {
        echo "  Aliases: " . implode(', ', $jobInfo['aliases']) . "\n";
    }
}
echo "\n";

// Example 1: Using job type string
echo "=== Example 1: Using job type string ===\n";
$workflow1 = $engine->createWorkflow('example1', 'Job Type String Example')
    ->addJob('http', 'fetch_api', 'Fetch API Data', [
        'url' => 'https://jsonplaceholder.typicode.com/users/1',
        'method' => 'GET',
    ])
    ->addJob('transform', 'process_data', 'Process Data', [
        'operation' => 'custom',
        'customCode' => 'return ["processed" => true, "data" => $input];',
    ])
    ->connect('fetch_api', 'process_data');

$execution1 = $engine->executeWorkflow($workflow1);
echo "Status: " . $execution1->getStatus() . "\n";
if ($execution1->isCompleted()) {
    $context = $execution1->getContext();
    echo "Result: " . json_encode($context['nodes']['process_data']['output']) . "\n";
}
echo "\n";

// Example 2: Using job aliases
echo "=== Example 2: Using job aliases ===\n";
$workflow2 = $engine->createWorkflow('example2', 'Job Aliases Example')
    ->addJob('api', 'fetch_posts', 'Fetch Posts', [
        'url' => 'https://jsonplaceholder.typicode.com/posts/1',
        'method' => 'GET',
    ])
    ->addJob('php', 'process_posts', 'Process Posts', [
        'code' => 'return ["post_id" => $input["id"], "title" => $input["title"]];',
    ])
    ->connect('fetch_posts', 'process_posts');

$execution2 = $engine->executeWorkflow($workflow2);
echo "Status: " . $execution2->getStatus() . "\n";
if ($execution2->isCompleted()) {
    $context = $execution2->getContext();
    echo "Result: " . json_encode($context['nodes']['process_posts']['output']) . "\n";
}
echo "\n";

// Example 3: Using class names
echo "=== Example 3: Using class names ===\n";
$workflow3 = $engine->createWorkflow('example3', 'Class Names Example')
    ->addJob(HttpNode::class, 'http_request', 'HTTP Request', [
        'url' => 'https://jsonplaceholder.typicode.com/comments/1',
        'method' => 'GET',
    ])
    ->addJob(CodeNode::class, 'code_process', 'Code Process', [
        'code' => 'return ["comment_id" => $input["id"], "email" => $input["email"]];',
    ])
    ->connect('http_request', 'code_process');

$execution3 = $engine->executeWorkflow($workflow3);
echo "Status: " . $execution3->getStatus() . "\n";
if ($execution3->isCompleted()) {
    $context = $execution3->getContext();
    echo "Result: " . json_encode($context['nodes']['code_process']['output']) . "\n";
}
echo "\n";

// Example 4: Using node instances
echo "=== Example 4: Using node instances ===\n";
$workflow4 = $engine->createWorkflow('example4', 'Node Instances Example')
    ->addJob(new HttpNode([
        'id' => 'http_node',
        'name' => 'HTTP Node',
        'url' => 'https://jsonplaceholder.typicode.com/albums/1',
        'method' => 'GET',
    ]))
    ->addJob(new CodeNode([
        'id' => 'code_node',
        'name' => 'Code Node',
        'code' => 'return ["album_id" => $input["id"], "title" => $input["title"]];',
    ]))
    ->connect('http_node', 'code_node');

$execution4 = $engine->executeWorkflow($workflow4);
echo "Status: " . $execution4->getStatus() . "\n";
if ($execution4->isCompleted()) {
    $context = $execution4->getContext();
    echo "Result: " . json_encode($context['nodes']['code_node']['output']) . "\n";
}
echo "\n";

// Example 5: Using array configuration
echo "=== Example 5: Using array configuration ===\n";
$workflow5 = $engine->createWorkflow('example5', 'Array Configuration Example')
    ->addJob([
        'type' => 'http',
        'id' => 'array_http',
        'name' => 'Array HTTP',
        'config' => [
            'url' => 'https://jsonplaceholder.typicode.com/photos/1',
            'method' => 'GET',
        ],
    ])
    ->addJob([
        'type' => 'code',
        'id' => 'array_code',
        'name' => 'Array Code',
        'config' => [
            'code' => 'return ["photo_id" => $input["id"], "title" => $input["title"]];',
        ],
    ])
    ->connect('array_http', 'array_code');

$execution5 = $engine->executeWorkflow($workflow5);
echo "Status: " . $execution5->getStatus() . "\n";
if ($execution5->isCompleted()) {
    $context = $execution5->getContext();
    echo "Result: " . json_encode($context['nodes']['array_code']['output']) . "\n";
}
echo "\n";

// Example 6: Using WorkflowBuilder with new API
echo "=== Example 6: WorkflowBuilder with new API ===\n";
$builder = new WorkflowBuilder($engine);

$result6 = $builder
    ->create('builder_example', 'Builder Example')
    ->addJob('http', 'builder_http', 'Builder HTTP', [
        'url' => 'https://jsonplaceholder.typicode.com/todos/1',
        'method' => 'GET',
    ])
    ->addJob('transform', 'builder_transform', 'Builder Transform', [
        'operation' => 'custom',
        'customCode' => 'return ["todo_id" => $input["id"], "completed" => $input["completed"]];',
    ])
    ->connect('builder_http', 'builder_transform')
    ->execute();

echo "Status: " . $result6->getStatus() . "\n";
if ($result6->isCompleted()) {
    $context = $result6->getContext();
    echo "Result: " . json_encode($context['nodes']['builder_transform']['output']) . "\n";
}
echo "\n";

// Example 7: Async jobs
echo "=== Example 7: Async jobs ===\n";
$workflow7 = $engine->createWorkflow('example7', 'Async Jobs Example')
    ->addJob('http', 'sync_start', 'Sync Start', [
        'url' => 'https://jsonplaceholder.typicode.com/users/1',
        'method' => 'GET',
    ])
    ->addAsyncJob('http', 'async_task1', 'Async Task 1', [
        'url' => 'https://jsonplaceholder.typicode.com/posts/1',
        'method' => 'GET',
    ])
    ->addAsyncJob('http', 'async_task2', 'Async Task 2', [
        'url' => 'https://jsonplaceholder.typicode.com/comments/1',
        'method' => 'GET',
    ])
    ->addJob('code', 'final_process', 'Final Process', [
        'code' => '$user = $context["nodes"]["sync_start"]["output"];\n$post = $context["nodes"]["async_task1"]["output"];\n$comment = $context["nodes"]["async_task2"]["output"];\nreturn [\n    "user" => $user["name"],\n    "post_title" => $post["title"],\n    "comment_email" => $comment["email"]\n];',
    ])
    ->connect('sync_start', 'async_task1')
    ->connect('sync_start', 'async_task2')
    ->connect('async_task1', 'final_process')
    ->connect('async_task2', 'final_process');

$execution7 = $engine->executeWorkflow($workflow7);
echo "Status: " . $execution7->getStatus() . "\n";
if ($execution7->isCompleted()) {
    $context = $execution7->getContext();
    echo "Result: " . json_encode($context['nodes']['final_process']['output']) . "\n";
}
echo "\n";

// Example 8: Custom job registration
echo "=== Example 8: Custom job registration ===\n";

// Define a custom job class
class CustomEmailJob extends \Rodoud\WorkflowEngine\Core\AbstractNode
{
    protected string $type = 'email';
    
    public function execute(array $context, array $input = []): array
    {
        $email = $this->config['email'] ?? '';
        $subject = $this->config['subject'] ?? '';
        $message = $this->config['message'] ?? '';
        
        // Simulate sending email
        $logs = [$this->log('info', "Sending email to {$email}: {$subject}")];
        
        return [
            'success' => true,
            'data' => [
                'email' => $email,
                'subject' => $subject,
                'sent_at' => date('Y-m-d H:i:s'),
            ],
            'logs' => $logs,
        ];
    }
    
    public function getDescription(): string
    {
        return 'Send email notifications';
    }
    
    public function getCategory(): string
    {
        return 'Communication';
    }
    
    public function getIcon(): string
    {
        return 'mail';
    }
}

// Register the custom job
$engine->registerJob('email', CustomEmailJob::class);

// Use the custom job
$workflow8 = $engine->createWorkflow('example8', 'Custom Job Example')
    ->addJob('http', 'fetch_user', 'Fetch User', [
        'url' => 'https://jsonplaceholder.typicode.com/users/1',
        'method' => 'GET',
    ])
    ->addJob('email', 'send_notification', 'Send Notification', [
        'email' => 'user@example.com',
        'subject' => 'User Data Retrieved',
        'message' => 'User data has been successfully retrieved.',
    ])
    ->connect('fetch_user', 'send_notification');

$execution8 = $engine->executeWorkflow($workflow8);
echo "Status: " . $execution8->getStatus() . "\n";
if ($execution8->isCompleted()) {
    $context = $execution8->getContext();
    echo "User: " . $context['nodes']['fetch_user']['output']['name'] . "\n";
    echo "Email sent to: " . $context['nodes']['send_notification']['output']['email'] . "\n";
}
echo "\n";

echo "=== All Job API Examples Completed! ===\n";