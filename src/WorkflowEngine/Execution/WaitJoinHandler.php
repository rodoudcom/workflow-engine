<?php

namespace App\WorkflowEngine\Execution;

use App\WorkflowEngine\Interface\NodeInterface;
use App\WorkflowEngine\Context\WorkflowContext;

class WaitJoinHandler
{
    protected DependencyGraph $dependencyGraph;
    protected array $waitForNodes = [];
    protected array $joinConditions = [];
    protected array $completedNodes = [];
    protected array $nodeResults = [];

    public function __construct(DependencyGraph $dependencyGraph)
    {
        $this->dependencyGraph = $dependencyGraph;
    }

    public function addWaitCondition(string $nodeId, array $waitForNodes, ?callable $condition = null): void
    {
        $this->waitForNodes[$nodeId] = $waitForNodes;
        $this->joinConditions[$nodeId] = $condition;
    }

    public function canExecuteNode(string $nodeId): bool
    {
        // Check if node has explicit wait conditions
        if (isset($this->waitForNodes[$nodeId])) {
            return $this->checkWaitCondition($nodeId);
        }

        // Default behavior: check if all dependencies are completed
        $dependencies = $this->dependencyGraph->getDependencies($nodeId);
        
        foreach ($dependencies as $dep) {
            if (!in_array($dep, $this->completedNodes)) {
                return false;
            }
        }

        return true;
    }

    protected function checkWaitCondition(string $nodeId): bool
    {
        $waitForNodes = $this->waitForNodes[$nodeId];
        
        // Check if all required nodes are completed
        foreach ($waitForNodes as $requiredNodeId) {
            if (!in_array($requiredNodeId, $this->completedNodes)) {
                return false;
            }
        }

        // Check custom condition if provided
        if (isset($this->joinConditions[$nodeId])) {
            $condition = $this->joinConditions[$nodeId];
            $requiredResults = [];
            
            foreach ($waitForNodes as $requiredNodeId) {
                $requiredResults[$requiredNodeId] = $this->nodeResults[$requiredNodeId] ?? null;
            }

            return $condition($requiredResults, $this->nodeResults);
        }

        return true;
    }

    public function markNodeCompleted(string $nodeId, $result = null): void
    {
        if (!in_array($nodeId, $this->completedNodes)) {
            $this->completedNodes[] = $nodeId;
            
            if ($result !== null) {
                $this->nodeResults[$nodeId] = $result;
            }
        }
    }

    public function markNodeFailed(string $nodeId): void
    {
        // Remove from completed if it was marked as completed
        $this->completedNodes = array_filter($this->completedNodes, function ($id) use ($nodeId) {
            return $id !== $nodeId;
        });

        // Remove result
        unset($this->nodeResults[$nodeId]);
    }

    public function getReadyNodes(array $availableNodes): array
    {
        $ready = [];
        
        foreach ($availableNodes as $node) {
            $nodeId = $node->getId();
            
            if (!in_array($nodeId, $this->completedNodes) && $this->canExecuteNode($nodeId)) {
                $ready[] = $node;
            }
        }
        
        return $ready;
    }

    public function getPendingNodes(array $availableNodes): array
    {
        $pending = [];
        
        foreach ($availableNodes as $node) {
            $nodeId = $node->getId();
            
            if (!in_array($nodeId, $this->completedNodes) && !$this->canExecuteNode($nodeId)) {
                $pending[] = $node;
            }
        }
        
        return $pending;
    }

    public function getCompletedNodes(): array
    {
        return $this->completedNodes;
    }

    public function getNodeResults(): array
    {
        return $this->nodeResults;
    }

    public function getNodeResult(string $nodeId)
    {
        return $this->nodeResults[$nodeId] ?? null;
    }

    public function createWaitForAll(string $nodeId, array $dependencyNodeIds): void
    {
        $this->addWaitCondition($nodeId, $dependencyNodeIds, function ($requiredResults) {
            // Wait for all dependencies to complete successfully
            foreach ($requiredResults as $result) {
                if ($result === null || (isset($result['success']) && !$result['success'])) {
                    return false;
                }
            }
            return true;
        });
    }

    public function createWaitForAny(string $nodeId, array $dependencyNodeIds): void
    {
        $this->addWaitCondition($nodeId, $dependencyNodeIds, function ($requiredResults) {
            // Wait for at least one dependency to complete successfully
            foreach ($requiredResults as $result) {
                if ($result !== null && (!isset($result['success']) || $result['success'])) {
                    return true;
                }
            }
            return false;
        });
    }

    public function createConditionalWait(string $nodeId, array $dependencyNodeIds, callable $condition): void
    {
        $this->addWaitCondition($nodeId, $dependencyNodeIds, $condition);
    }

    public function createDataBasedWait(string $nodeId, array $dependencyNodeIds, string $expression): void
    {
        $this->addWaitCondition($nodeId, $dependencyNodeIds, function ($requiredResults) use ($expression) {
            // Create a safe evaluation context
            $variables = [];
            foreach ($requiredResults as $depNodeId => $result) {
                $variables[$depNodeId] = $result;
            }

            try {
                // Extract variables for evaluation
                extract($variables);
                
                // Evaluate the expression
                return eval("return ({$expression});");
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    public function getExecutionPlan(array $allNodes): array
    {
        $plan = [];
        $remainingNodes = $allNodes;
        $iteration = 0;

        while (!empty($remainingNodes) && $iteration < 100) { // Prevent infinite loops
            $iteration++;
            
            $readyNodes = $this->getReadyNodes($remainingNodes);
            $pendingNodes = $this->getPendingNodes($remainingNodes);

            if (empty($readyNodes) && !empty($pendingNodes)) {
                // Deadlock detected
                throw new \Exception("Deadlock detected: No nodes can be executed but there are pending nodes");
            }

            if (!empty($readyNodes)) {
                $plan[] = [
                    'iteration' => $iteration,
                    'ready_nodes' => array_map(fn($node) => $node->getId(), $readyNodes),
                    'pending_nodes' => array_map(fn($node) => $node->getId(), $pendingNodes),
                    'can_execute_parallel' => count($readyNodes) > 1,
                ];

                // Mark ready nodes as completed for planning purposes
                foreach ($readyNodes as $node) {
                    $this->markNodeCompleted($node->getId(), ['success' => true]);
                }

                // Remove ready nodes from remaining
                $remainingNodes = array_filter($remainingNodes, function ($node) use ($readyNodes) {
                    return !in_array($node, $readyNodes);
                });
            }
        }

        // Reset state for actual execution
        $this->completedNodes = [];
        $this->nodeResults = [];

        return $plan;
    }

    public function validateWaitConditions(): array
    {
        $errors = [];

        foreach ($this->waitForNodes as $nodeId => $waitForNodes) {
            // Check if wait nodes exist
            foreach ($waitForNodes as $waitNodeId) {
                if (!$this->dependencyGraph->getDependencies($nodeId) || 
                    !in_array($waitNodeId, $this->dependencyGraph->getDependencies($nodeId))) {
                    $errors[] = "Node {$nodeId} is waiting for {$waitNodeId} but it's not a direct dependency";
                }
            }
        }

        return $errors;
    }

    public function getWaitStatistics(): array
    {
        $stats = [
            'total_wait_conditions' => count($this->waitForNodes),
            'completed_nodes' => count($this->completedNodes),
            'pending_nodes' => 0,
            'wait_conditions' => [],
        ];

        foreach ($this->waitForNodes as $nodeId => $waitForNodes) {
            $completed = 0;
            $pending = 0;

            foreach ($waitForNodes as $waitNodeId) {
                if (in_array($waitNodeId, $this->completedNodes)) {
                    $completed++;
                } else {
                    $pending++;
                }
            }

            $stats['wait_conditions'][$nodeId] = [
                'waiting_for' => $waitForNodes,
                'completed' => $completed,
                'pending' => $pending,
                'can_execute' => $pending === 0,
            ];

            $stats['pending_nodes'] += $pending;
        }

        return $stats;
    }

    public function reset(): void
    {
        $this->completedNodes = [];
        $this->nodeResults = [];
    }

    public function toArray(): array
    {
        return [
            'wait_conditions' => $this->waitForNodes,
            'join_conditions' => array_keys($this->joinConditions),
            'completed_nodes' => $this->completedNodes,
            'node_results' => array_keys($this->nodeResults),
            'statistics' => $this->getWaitStatistics(),
        ];
    }
}