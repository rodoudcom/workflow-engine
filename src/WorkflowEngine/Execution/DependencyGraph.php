<?php

namespace Rodoud\WorkflowEngine\Execution;

use Rodoud\WorkflowEngine\Interface\WorkflowInterface;
use Rodoud\WorkflowEngine\Interface\NodeInterface;

class DependencyGraph
{
    protected array $nodes = [];
    protected array $dependencies = [];
    protected array $dependents = [];
    protected array $executionLevels = [];

    public function __construct(WorkflowInterface $workflow)
    {
        $this->buildGraph($workflow);
    }

    protected function buildGraph(WorkflowInterface $workflow): void
    {
        $this->nodes = $workflow->getNodes();
        
        // Initialize dependencies and dependents
        foreach ($this->nodes as $nodeId => $node) {
            $this->dependencies[$nodeId] = [];
            $this->dependents[$nodeId] = [];
        }

        // Build dependency graph from connections
        foreach ($workflow->getConnections() as $connection) {
            $fromNode = $connection['from'];
            $toNode = $connection['to'];
            
            $this->dependencies[$toNode][] = $fromNode;
            $this->dependents[$fromNode][] = $toNode;
        }

        // Calculate execution levels
        $this->calculateExecutionLevels();
    }

    protected function calculateExecutionLevels(): void
    {
        $visited = [];
        $levels = [];
        $queue = [];

        // Find start nodes (nodes with no dependencies)
        foreach ($this->nodes as $nodeId => $node) {
            if (empty($this->dependencies[$nodeId])) {
                $queue[] = ['node' => $nodeId, 'level' => 0];
                $visited[$nodeId] = true;
            }
        }

        // Process nodes in BFS order
        while (!empty($queue)) {
            $current = array_shift($queue);
            $nodeId = $current['node'];
            $level = $current['level'];
            
            $this->executionLevels[$nodeId] = $level;
            $levels[$level][] = $nodeId;

            // Process dependents
            foreach ($this->dependents[$nodeId] as $dependent) {
                if (!isset($visited[$dependent])) {
                    // Check if all dependencies are visited
                    $allDependenciesVisited = true;
                    foreach ($this->dependencies[$dependent] as $dep) {
                        if (!isset($visited[$dep])) {
                            $allDependenciesVisited = false;
                            break;
                        }
                    }

                    if ($allDependenciesVisited) {
                        $queue[] = ['node' => $dependent, 'level' => $level + 1];
                        $visited[$dependent] = true;
                    }
                }
            }
        }

        // Handle circular dependencies (if any)
        foreach ($this->nodes as $nodeId => $node) {
            if (!isset($visited[$nodeId])) {
                $this->executionLevels[$nodeId] = count($levels);
                $levels[count($levels)][] = $nodeId;
            }
        }
    }

    public function getExecutionLevels(): array
    {
        return $this->executionLevels;
    }

    public function getNodesAtLevel(int $level): array
    {
        $nodes = [];
        foreach ($this->executionLevels as $nodeId => $nodeLevel) {
            if ($nodeLevel === $level) {
                $nodes[] = $this->nodes[$nodeId];
            }
        }
        return $nodes;
    }

    public function getDependencies(string $nodeId): array
    {
        return $this->dependencies[$nodeId] ?? [];
    }

    public function getDependents(string $nodeId): array
    {
        return $this->dependents[$nodeId] ?? [];
    }

    public function canExecute(string $nodeId, array $completedNodes): bool
    {
        $dependencies = $this->getDependencies($nodeId);
        
        foreach ($dependencies as $dep) {
            if (!in_array($dep, $completedNodes)) {
                return false;
            }
        }
        
        return true;
    }

    public function getReadyNodes(array $completedNodes): array
    {
        $ready = [];
        
        foreach ($this->nodes as $nodeId => $node) {
            if (!in_array($nodeId, $completedNodes) && $this->canExecute($nodeId, $completedNodes)) {
                $ready[] = $node;
            }
        }
        
        return $ready;
    }

    public function getParallelGroups(): array
    {
        $groups = [];
        
        foreach ($this->executionLevels as $nodeId => $level) {
            $groups[$level][] = $nodeId;
        }
        
        return array_values($groups);
    }

    public function getNodeDependencies(string $nodeId): array
    {
        $dependencies = [];
        $queue = [$nodeId];
        $visited = [$nodeId];

        while (!empty($queue)) {
            $current = array_shift($queue);
            
            foreach ($this->dependencies[$current] as $dep) {
                if (!in_array($dep, $visited)) {
                    $dependencies[] = $dep;
                    $queue[] = $dep;
                    $visited[] = $dep;
                }
            }
        }

        return $dependencies;
    }

    public function getNodeDependents(string $nodeId): array
    {
        $dependents = [];
        $queue = [$nodeId];
        $visited = [$nodeId];

        while (!empty($queue)) {
            $current = array_shift($queue);
            
            foreach ($this->dependents[$current] as $dep) {
                if (!in_array($dep, $visited)) {
                    $dependents[] = $dep;
                    $queue[] = $dep;
                    $visited[] = $dep;
                }
            }
        }

        return $dependents;
    }

    public function validateGraph(): array
    {
        $errors = [];
        
        // Check for circular dependencies
        $visited = [];
        $recursionStack = [];
        
        foreach ($this->nodes as $nodeId => $node) {
            if (!isset($visited[$nodeId])) {
                if ($this->hasCircularDependency($nodeId, $visited, $recursionStack)) {
                    $errors[] = "Circular dependency detected involving node: {$nodeId}";
                }
            }
        }
        
        // Check for orphaned nodes (except start nodes)
        $startNodes = $this->getStartNodes();
        foreach ($this->nodes as $nodeId => $node) {
            if (!in_array($nodeId, $startNodes) && empty($this->dependencies[$nodeId])) {
                $errors[] = "Orphaned node detected: {$nodeId} (no dependencies and not a start node)";
            }
        }
        
        return $errors;
    }

    protected function hasCircularDependency(string $nodeId, array &$visited, array &$recursionStack): bool
    {
        $visited[$nodeId] = true;
        $recursionStack[$nodeId] = true;

        foreach ($this->dependents[$nodeId] as $dependent) {
            if (!isset($visited[$dependent])) {
                if ($this->hasCircularDependency($dependent, $visited, $recursionStack)) {
                    return true;
                }
            } elseif (isset($recursionStack[$dependent])) {
                return true;
            }
        }

        unset($recursionStack[$nodeId]);
        return false;
    }

    public function getStartNodes(): array
    {
        $startNodes = [];
        
        foreach ($this->nodes as $nodeId => $node) {
            if (empty($this->dependencies[$nodeId])) {
                $startNodes[] = $nodeId;
            }
        }
        
        return $startNodes;
    }

    public function getEndNodes(): array
    {
        $endNodes = [];
        
        foreach ($this->nodes as $nodeId => $node) {
            if (empty($this->dependents[$nodeId])) {
                $endNodes[] = $nodeId;
            }
        }
        
        return $endNodes;
    }

    public function getCriticalPath(): array
    {
        // Calculate the longest path through the graph
        $longestPaths = [];
        
        // Start from end nodes and work backwards
        foreach ($this->getEndNodes() as $endNode) {
            $path = $this->calculateLongestPath($endNode, $longestPaths);
            if (empty($longestPaths) || count($path) > count($longestPaths)) {
                $longestPaths = $path;
            }
        }
        
        return array_reverse($longestPaths);
    }

    protected function calculateLongestPath(string $nodeId, array &$memo): array
    {
        if (isset($memo[$nodeId])) {
            return $memo[$nodeId];
        }

        $maxPath = [];
        
        foreach ($this->dependencies[$nodeId] as $dep) {
            $path = $this->calculateLongestPath($dep, $memo);
            if (count($path) > count($maxPath)) {
                $maxPath = $path;
            }
        }
        
        $maxPath[] = $nodeId;
        $memo[$nodeId] = $maxPath;
        
        return $maxPath;
    }

    public function toArray(): array
    {
        return [
            'nodes' => array_keys($this->nodes),
            'dependencies' => $this->dependencies,
            'dependents' => $this->dependents,
            'executionLevels' => $this->executionLevels,
            'parallelGroups' => $this->getParallelGroups(),
            'startNodes' => $this->getStartNodes(),
            'endNodes' => $this->getEndNodes(),
            'criticalPath' => $this->getCriticalPath(),
        ];
    }
}