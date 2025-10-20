<?php

namespace Rodoud\WorkflowEngine\Execution;

use Rodoud\WorkflowEngine\Interface\WorkflowInterface;
use Rodoud\WorkflowEngine\Interface\ExecutionInterface;
use Rodoud\WorkflowEngine\Interface\NodeInterface;
use Rodoud\WorkflowEngine\Core\Execution;
use Rodoud\WorkflowEngine\Context\WorkflowContext;
use Rodoud\WorkflowEngine\Logger\WorkflowLogger;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\WorkerPool;
use Amp\Promise;

class MixedWorkflowExecutor extends WorkflowExecutor
{
    protected WorkerPool $workerPool;
    protected DependencyGraph $dependencyGraph;
    protected array $nodeResults = [];
    protected array $runningNodes = [];
    protected WorkflowLogger $logger;

    public function __construct(array $redisConfig = [], int $maxWorkers = 4)
    {
        parent::__construct($redisConfig, true);
        $this->workerPool = new WorkerPool($maxWorkers);
        $this->logger = new WorkflowLogger($redisConfig, 'info');
    }

    public function execute(WorkflowInterface $workflow, array $initialContext = []): ExecutionInterface
    {
        $executionId = uniqid('exec_', true);
        $execution = new Execution($executionId, $workflow->getId(), $initialContext);
        
        if (!$workflow->validate()) {
            $execution->fail('Workflow validation failed');
            return $execution;
        }

        // Build dependency graph
        $this->dependencyGraph = new DependencyGraph($workflow);
        
        // Validate graph
        $errors = $this->dependencyGraph->validateGraph();
        if (!empty($errors)) {
            $execution->fail('Dependency graph validation failed: ' . implode(', ', $errors));
            return $execution;
        }

        $execution->start();
        $this->saveExecutionToRedis($execution);

        try {
            $this->executeMixed($workflow, $execution);
        } catch (\Exception $e) {
            $execution->fail($e->getMessage());
            $this->saveExecutionToRedis($execution);
        }

        return $execution;
    }

    protected function executeMixed(WorkflowInterface $workflow, ExecutionInterface $execution): void
    {
        $context = new WorkflowContext($execution->getContext());
        $completedNodes = [];
        $failedNodes = [];
        $this->nodeResults = [];
        $this->runningNodes = [];

        // Get execution levels (parallel groups)
        $parallelGroups = $this->dependencyGraph->getParallelGroups();

        foreach ($parallelGroups as $level => $nodeIds) {
            $this->logger->log('info', "Executing level {$level} with " . count($nodeIds) . " nodes", [], $execution->getId());

            // Separate sync and async nodes in this level
            $syncNodes = [];
            $asyncNodes = [];

            foreach ($nodeIds as $nodeId) {
                $node = $workflow->getNode($nodeId);
                if ($node->getExecutionMode() === 'async') {
                    $asyncNodes[] = $node;
                } else {
                    $syncNodes[] = $node;
                }
            }

            // Execute sync nodes first (they might be dependencies for async nodes)
            foreach ($syncNodes as $node) {
                if ($this->shouldExecuteNode($node, $completedNodes, $failedNodes)) {
                    $this->executeSyncNode($node, $context, $execution, $completedNodes, $failedNodes);
                }
            }

            // Execute async nodes in parallel
            if (!empty($asyncNodes)) {
                $this->executeAsyncNodes($asyncNodes, $context, $execution, $completedNodes, $failedNodes);
            }

            // Check if we should continue
            if (!empty($failedNodes)) {
                $execution->fail('Some nodes failed: ' . implode(', ', $failedNodes));
                $this->saveExecutionToRedis($execution);
                return;
            }

            // Update execution context
            $execution->setContext($context->all());
            $this->saveExecutionToRedis($execution);
        }

        $execution->complete();
        $this->saveExecutionToRedis($execution);
        $this->saveExecutionToHistory($execution);
    }

    protected function shouldExecuteNode(NodeInterface $node, array $completedNodes, array $failedNodes): bool
    {
        $nodeId = $node->getId();

        // Skip if already completed or failed
        if (in_array($nodeId, $completedNodes) || in_array($nodeId, $failedNodes)) {
            return false;
        }

        // Check if all dependencies are completed
        $dependencies = $this->dependencyGraph->getDependencies($nodeId);
        foreach ($dependencies as $dep) {
            if (!in_array($dep, $completedNodes)) {
                return false;
            }
            if (in_array($dep, $failedNodes)) {
                return false;
            }
        }

        return true;
    }

    protected function executeSyncNode(NodeInterface $node, WorkflowContext $context, ExecutionInterface $execution, array &$completedNodes, array &$failedNodes): void
    {
        $nodeId = $node->getId();
        
        try {
            $this->logger->log('info', "Executing sync node: {$node->getName()}", [], $execution->getId(), $nodeId);
            
            // Get input from dependencies
            $input = $this->getNodeInput($node, $context->all());
            
            // Execute the node
            $result = $node->execute($context->all(), $input);
            
            // Add logs to execution
            if (isset($result['logs'])) {
                foreach ($result['logs'] as $log) {
                    $execution->addLog($nodeId, $log['level'], $log['message'], $log['data'] ?? []);
                }
            }

            // Check if node execution failed
            if (!$result['success']) {
                $this->logger->log('error', "Sync node failed: {$node->getName()}", ['error' => $result['error']], $execution->getId(), $nodeId);
                
                if ($node->getStopWorkflowOnFail()) {
                    $failedNodes[] = $nodeId;
                    return;
                }
            }

            // Update context with node output
            if (isset($result['data'])) {
                $context->set("nodes.{$nodeId}.output", $result['data']);
                $this->nodeResults[$nodeId] = $result['data'];
            }

            $completedNodes[] = $nodeId;
            $this->logger->log('info', "Sync node completed: {$node->getName()}", [], $execution->getId(), $nodeId);

        } catch (\Exception $e) {
            $this->logger->log('error', "Sync node exception: {$node->getName()}", ['error' => $e->getMessage()], $execution->getId(), $nodeId);
            
            if ($node->getStopWorkflowOnFail()) {
                $failedNodes[] = $nodeId;
            } else {
                $completedNodes[] = $nodeId;
            }
        }
    }

    protected function executeAsyncNodes(array $nodes, WorkflowContext $context, ExecutionInterface $execution, array &$completedNodes, array &$failedNodes): void
    {
        $promises = [];
        $nodeMap = [];

        // Create async tasks for each node
        foreach ($nodes as $node) {
            if ($this->shouldExecuteNode($node, $completedNodes, $failedNodes)) {
                $nodeId = $node->getId();
                $nodeMap[$nodeId] = $node;
                
                // Get input from dependencies
                $input = $this->getNodeInput($node, $context->all());
                
                // Create async task
                $task = new NodeExecutionTask($node, $context->all(), $input);
                $promises[$nodeId] = $this->workerPool->enqueue($task);
                
                $this->runningNodes[$nodeId] = $node;
                $this->logger->log('info', "Started async node: {$node->getName()}", [], $execution->getId(), $nodeId);
            }
        }

        // Wait for all async nodes to complete
        if (!empty($promises)) {
            $results = \Amp\Promise\wait(\Amp\Promise\all($promises));
            
            foreach ($results as $nodeId => $result) {
                $node = $nodeMap[$nodeId];
                
                try {
                    // Add logs to execution
                    if (isset($result['logs'])) {
                        foreach ($result['logs'] as $log) {
                            $execution->addLog($nodeId, $log['level'], $log['message'], $log['data'] ?? []);
                        }
                    }

                    // Check if node execution failed
                    if (!$result['success']) {
                        $this->logger->log('error', "Async node failed: {$node->getName()}", ['error' => $result['error']], $execution->getId(), $nodeId);
                        
                        if ($node->getStopWorkflowOnFail()) {
                            $failedNodes[] = $nodeId;
                            continue;
                        }
                    }

                    // Update context with node output
                    if (isset($result['data'])) {
                        $context->set("nodes.{$nodeId}.output", $result['data']);
                        $this->nodeResults[$nodeId] = $result['data'];
                    }

                    $completedNodes[] = $nodeId;
                    $this->logger->log('info', "Async node completed: {$node->getName()}", [], $execution->getId(), $nodeId);

                } catch (\Exception $e) {
                    $this->logger->log('error', "Async node exception: {$node->getName()}", ['error' => $e->getMessage()], $execution->getId(), $nodeId);
                    
                    if ($node->getStopWorkflowOnFail()) {
                        $failedNodes[] = $nodeId;
                    } else {
                        $completedNodes[] = $nodeId;
                    }
                }
                
                unset($this->runningNodes[$nodeId]);
            }
        }
    }

    protected function getNodeInput(NodeInterface $node, array $context): array
    {
        $input = [];
        $nodeId = $node->getId();

        // Get inputs from dependency graph
        $dependencies = $this->dependencyGraph->getDependencies($nodeId);
        
        foreach ($dependencies as $depNodeId) {
            if (isset($this->nodeResults[$depNodeId])) {
                $input[$depNodeId] = $this->nodeResults[$depNodeId];
            }
        }

        // Also check context for any additional inputs
        if (isset($context["nodes.{$nodeId}.input"])) {
            $input = array_merge($input, $context["nodes.{$nodeId}.input"]);
        }

        return $input;
    }

    public function getDependencyGraph(): DependencyGraph
    {
        return $this->dependencyGraph;
    }

    public function getNodeResults(): array
    {
        return $this->nodeResults;
    }

    public function getRunningNodes(): array
    {
        return $this->runningNodes;
    }

    public function __destruct()
    {
        $this->workerPool->shutdown();
    }
}

class NodeExecutionTask implements Task
{
    private NodeInterface $node;
    private array $context;
    private array $input;

    public function __construct(NodeInterface $node, array $context, array $input)
    {
        $this->node = $node;
        $this->context = $context;
        $this->input = $input;
    }

    public function run(): array
    {
        try {
            return $this->node->execute($this->context, $this->input);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => [[
                    'level' => 'error',
                    'message' => 'Task execution failed: ' . $e->getMessage(),
                    'data' => ['trace' => $e->getTraceAsString()],
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]],
            ];
        }
    }
}