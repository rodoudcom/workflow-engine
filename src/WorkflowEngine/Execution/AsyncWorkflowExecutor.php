<?php

namespace App\WorkflowEngine\Execution;

use App\WorkflowEngine\Interface\WorkflowInterface;
use Amp\Parallel\Worker\Task;
use Amp\Parallel\Worker\Worker;
use Amp\Parallel\Worker\WorkerPool;
use Amp\Promise;

class AsyncWorkflowExecutor extends WorkflowExecutor
{
    protected WorkerPool $workerPool;

    public function __construct(array $redisConfig = [], int $maxWorkers = 4)
    {
        parent::__construct($redisConfig, true);
        $this->workerPool = new WorkerPool($maxWorkers);
    }

    public function executeAsync(WorkflowInterface $workflow, array $initialContext = []): Promise
    {
        return \Amp\call(function () use ($workflow, $initialContext) {
            $task = new WorkflowExecutionTask($workflow->toArray(), $initialContext);
            $worker = $this->workerPool->getWorker();
            
            try {
                $result = yield $worker->enqueue($task);
                return Execution::fromArray($result);
            } finally {
                $this->workerPool->release($worker);
            }
        });
    }

    public function executeMultipleAsync(array $workflows, array $contexts = []): Promise
    {
        return \Amp\call(function () use ($workflows, $contexts) {
            $promises = [];
            
            foreach ($workflows as $index => $workflow) {
                $context = $contexts[$index] ?? [];
                $promises[] = $this->executeAsync($workflow, $context);
            }
            
            return yield $promises;
        });
    }

    public function __destruct()
    {
        $this->workerPool->shutdown();
    }
}

class WorkflowExecutionTask implements Task
{
    private array $workflowData;
    private array $initialContext;

    public function __construct(array $workflowData, array $initialContext = [])
    {
        $this->workflowData = $workflowData;
        $this->initialContext = $initialContext;
    }

    public function run(): array
    {
        // Reconstruct workflow from data
        $workflow = \App\WorkflowEngine\Core\Workflow::fromArray($this->workflowData);
        
        // Create a sync executor for this task
        $executor = new WorkflowExecutor([], false);
        
        // Execute the workflow
        $execution = $executor->execute($workflow, $this->initialContext);
        
        return $execution->toArray();
    }
}