<?php

namespace App\WorkflowEngine\Execution;

use App\WorkflowEngine\Interface\WorkflowInterface;
use App\WorkflowEngine\Interface\ExecutionInterface;
use App\WorkflowEngine\Interface\NodeInterface;
use App\WorkflowEngine\Core\Execution;
use App\WorkflowEngine\Context\WorkflowContext;

class WorkflowExecutor
{
    protected ?\Predis\Client $redis = null;
    protected array $redisConfig = [];
    protected bool $asyncMode = false;

    public function __construct(array $redisConfig = [], bool $asyncMode = false)
    {
        $this->redisConfig = $redisConfig;
        $this->asyncMode = $asyncMode;
        $this->initializeRedis();
    }

    public function execute(WorkflowInterface $workflow, array $initialContext = []): ExecutionInterface
    {
        $executionId = uniqid('exec_', true);
        $execution = new Execution($executionId, $workflow->getId(), $initialContext);
        
        if (!$workflow->validate()) {
            $execution->fail('Workflow validation failed');
            return $execution;
        }

        $execution->start();
        $this->saveExecutionToRedis($execution);

        try {
            if ($this->asyncMode) {
                $this->executeAsync($workflow, $execution);
            } else {
                $this->executeSync($workflow, $execution);
            }
        } catch (\Exception $e) {
            $execution->fail($e->getMessage());
            $this->saveExecutionToRedis($execution);
        }

        return $execution;
    }

    protected function executeSync(WorkflowInterface $workflow, ExecutionInterface $execution): void
    {
        $context = new WorkflowContext($execution->getContext());
        $visitedNodes = [];
        $nodeQueue = $this->getStartNodes($workflow);

        while (!empty($nodeQueue)) {
            $node = array_shift($nodeQueue);
            $nodeId = $node->getId();

            if (in_array($nodeId, $visitedNodes)) {
                continue;
            }

            $visitedNodes[] = $nodeId;

            // Get input from connected nodes
            $input = $this->getNodeInput($workflow, $node, $context->all());
            
            // Execute the node
            $result = $node->execute($context->all(), $input);
            
            // Add logs to execution
            if (isset($result['logs'])) {
                foreach ($result['logs'] as $log) {
                    $execution->addLog($nodeId, $log['level'], $log['message'], $log['data'] ?? []);
                }
            }

            // Check if node execution failed
            if (!$result['success'] && $node->getStopWorkflowOnFail()) {
                $execution->fail($result['error'] ?? 'Node execution failed');
                $this->saveExecutionToRedis($execution);
                return;
            }

            // Update context with node output
            if (isset($result['data'])) {
                $context->set("nodes.{$nodeId}.output", $result['data']);
                
                // Update execution context
                $execution->setContext($context->all());
                $this->saveExecutionToRedis($execution);
            }

            // Add next nodes to queue
            $nextNodes = $workflow->getNextNodes($nodeId);
            foreach ($nextNodes as $nextNode) {
                if (!in_array($nextNode->getId(), $visitedNodes)) {
                    $nodeQueue[] = $nextNode;
                }
            }
        }

        $execution->complete();
        $this->saveExecutionToRedis($execution);
        $this->saveExecutionToHistory($execution);
    }

    protected function executeAsync(WorkflowInterface $workflow, ExecutionInterface $execution): void
    {
        // Async execution using basic PHP implementation
        // For now, we'll implement a basic version
        $this->executeSync($workflow, $execution);
    }

    protected function getStartNodes(WorkflowInterface $workflow): array
    {
        $startNodes = [];
        $connectedNodes = [];

        foreach ($workflow->getConnections() as $connection) {
            $connectedNodes[] = $connection['to'];
        }

        foreach ($workflow->getNodes() as $node) {
            if (!in_array($node->getId(), $connectedNodes)) {
                $startNodes[] = $node;
            }
        }

        return $startNodes;
    }

    protected function getNodeInput(WorkflowInterface $workflow, NodeInterface $node, array $context): array
    {
        $input = [];
        $nodeId = $node->getId();

        foreach ($workflow->getConnections() as $connection) {
            if ($connection['to'] === $nodeId) {
                $fromNodeId = $connection['from'];
                $fromOutput = $connection['fromOutput'];
                $toInput = $connection['toInput'];

                if (isset($context["nodes.{$fromNodeId}.output.{$fromOutput}"])) {
                    $input[$toInput] = $context["nodes.{$fromNodeId}.output.{$fromOutput}"];
                } elseif (isset($context["nodes.{$fromNodeId}.output"])) {
                    $input[$toInput] = $context["nodes.{$fromNodeId}.output"];
                }
            }
        }

        return $input;
    }

    protected function initializeRedis(): void
    {
        if (empty($this->redisConfig)) {
            return; // Skip Redis if not configured
        }

        try {
            $parameters = [
                'scheme' => $this->redisConfig['scheme'] ?? 'tcp',
                'host' => $this->redisConfig['host'] ?? '127.0.0.1',
                'port' => $this->redisConfig['port'] ?? 6379,
            ];

            $options = [];

            // Add password if provided
            if (!empty($this->redisConfig['password'])) {
                $parameters['password'] = $this->redisConfig['password'];
            }

            // Add database if provided
            if (isset($this->redisConfig['database'])) {
                $parameters['database'] = $this->redisConfig['database'];
            }

            // Add connection timeout
            if (isset($this->redisConfig['timeout'])) {
                $parameters['timeout'] = $this->redisConfig['timeout'];
            }

            // Add read/write timeout
            if (isset($this->redisConfig['read_write_timeout'])) {
                $parameters['read_write_timeout'] = $this->redisConfig['read_write_timeout'];
            }

            // Add prefix if provided
            if (!empty($this->redisConfig['prefix'])) {
                $options['prefix'] = $this->redisConfig['prefix'];
            }

            $this->redis = new \Predis\Client($parameters, $options);
            
            // Test connection
            $this->redis->connect();

        } catch (\Exception $e) {
            throw new \Exception("Failed to connect to Redis: " . $e->getMessage());
        }
    }

    protected function saveExecutionToRedis(ExecutionInterface $execution): void
    {
        if (!$this->redis) {
            return;
        }

        $key = "workflow_execution:{$execution->getId()}";
        $data = json_encode($execution->toArray());
        
        $this->redis->setex($key, 3600, $data); // Keep for 1 hour
        
        // Also add to running executions list
        if ($execution->isRunning()) {
            $this->redis->sadd("running_executions", $execution->getId());
        } else {
            $this->redis->srem("running_executions", $execution->getId());
        }
    }

    protected function saveExecutionToHistory(ExecutionInterface $execution): void
    {
        if (!$this->redis) {
            return;
        }

        $key = "workflow_history:{$execution->getWorkflowId()}";
        $data = $execution->toArray();
        
        // Add to history list (keep last 100 executions)
        $this->redis->lpush($key, json_encode($data));
        $this->redis->ltrim($key, 0, 99);
        
        // Set expiration on history key
        $this->redis->expire($key, 86400 * 7); // Keep for 7 days
    }

    public function getExecution(string $executionId): ?ExecutionInterface
    {
        if (!$this->redis) {
            return null;
        }

        $key = "workflow_execution:{$executionId}";
        $data = $this->redis->get($key);
        
        if (!$data) {
            return null;
        }

        $executionData = json_decode($data, true);
        return Execution::fromArray($executionData);
    }

    public function getRunningExecutions(): array
    {
        if (!$this->redis) {
            return [];
        }

        $executionIds = $this->redis->smembers("running_executions");
        $executions = [];

        foreach ($executionIds as $executionId) {
            $execution = $this->getExecution($executionId);
            if ($execution && $execution->isRunning()) {
                $executions[] = $execution;
            }
        }

        return $executions;
    }

    public function getWorkflowHistory(string $workflowId): array
    {
        if (!$this->redis) {
            return [];
        }

        $key = "workflow_history:{$workflowId}";
        $historyData = $this->redis->lrange($key, 0, -1);
        
        $history = [];
        foreach ($historyData as $data) {
            $executionData = json_decode($data, true);
            $history[] = Execution::fromArray($executionData);
        }

        return $history;
    }

    public function cancelExecution(string $executionId): bool
    {
        $execution = $this->getExecution($executionId);
        
        if (!$execution || !$execution->isRunning()) {
            return false;
        }

        $execution->fail('Execution cancelled');
        $this->saveExecutionToRedis($execution);
        
        return true;
    }
}