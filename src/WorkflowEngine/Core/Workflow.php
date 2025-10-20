<?php

namespace Rodoud\WorkflowEngine\Core;

use Rodoud\WorkflowEngine\Interface\WorkflowInterface;
use Rodoud\WorkflowEngine\Interface\NodeInterface;
use Rodoud\WorkflowEngine\Registry\JobRegistry;

class Workflow implements WorkflowInterface
{
    protected string $id;
    protected string $name;
    protected string $description;
    protected array $nodes = [];
    protected array $connections = [];
    protected static ?JobRegistry $jobRegistry = null;

    public function __construct(string $id, string $name, string $description = '')
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function getConnections(): array
    {
        return $this->connections;
    }

    public function addNode(NodeInterface $node): self
    {
        $this->nodes[$node->getId()] = $node;
        return $this;
    }

    /**
     * Add a job to the workflow - flexible method supporting multiple approaches
     * 
     * Usage examples:
     * ->addJob('http', 'fetch_api', 'Fetch API Data', ['url' => 'https://api.example.com'])
     * ->addJob('httpRequest', 'fetch_api', 'Fetch API Data', ['url' => 'https://api.example.com'])
     * ->addJob(HttpNode::class, 'fetch_api', 'Fetch API Data', ['url' => 'https://api.example.com'])
     * ->addJob(new HttpNode(['id' => 'fetch_api', 'name' => 'Fetch API Data', 'url' => 'https://api.example.com']))
     * ->addJob(['type' => 'http', 'id' => 'fetch_api', 'name' => 'Fetch API Data', 'config' => ['url' => 'https://api.example.com']])
     */
    public function addJob($jobTypeOrClassOrInstance, string $id = null, string $name = null, array $config = []): self
    {
        $node = $this->createJobNode($jobTypeOrClassOrInstance, $id, $name, $config);
        return $this->addNode($node);
    }

    /**
     * Add a job with async execution mode
     */
    public function addAsyncJob($jobTypeOrClassOrInstance, string $id = null, string $name = null, array $config = []): self
    {
        $config['executionMode'] = 'async';
        return $this->addJob($jobTypeOrClassOrInstance, $id, $name, $config);
    }

    /**
     * Create a job node from various input types
     */
    protected function createJobNode($jobTypeOrClassOrInstance, string $id = null, string $name = null, array $config = []): NodeInterface
    {
        // If it's already a NodeInterface instance
        if ($jobTypeOrClassOrInstance instanceof NodeInterface) {
            return $jobTypeOrClassOrInstance;
        }

        // If it's an array configuration
        if (is_array($jobTypeOrClassOrInstance)) {
            return $this->createNodeFromArray($jobTypeOrClassOrInstance);
        }

        // If it's a class name
        if (class_exists($jobTypeOrClassOrInstance)) {
            return $this->createNodeFromClass($jobTypeOrClassOrInstance, $id, $name, $config);
        }

        // If it's a job type string
        return $this->createNodeFromType($jobTypeOrClassOrInstance, $id, $name, $config);
    }

    /**
     * Create node from array configuration
     */
    protected function createNodeFromArray(array $config): NodeInterface
    {
        $type = $config['type'] ?? $config['jobType'] ?? null;
        if (!$type) {
            throw new \InvalidArgumentException('Array configuration must contain "type" or "jobType"');
        }

        $id = $config['id'] ?? uniqid('node_', true);
        $name = $config['name'] ?? $id;
        $nodeConfig = $config['config'] ?? [];

        return $this->createNodeFromType($type, $id, $name, $nodeConfig);
    }

    /**
     * Create node from class name
     */
    protected function createNodeFromClass(string $className, string $id = null, string $name = null, array $config = []): NodeInterface
    {
        if (!class_exists($className)) {
            throw new \ClassNotFoundException("Class '{$className}' not found");
        }

        $nodeConfig = array_merge($config, [
            'id' => $id ?? uniqid('node_', true),
            'name' => $name ?? $id ?? 'Unnamed Job'
        ]);

        $node = new $className($nodeConfig);
        
        if (!$node instanceof NodeInterface) {
            throw new \InvalidArgumentException("Class '{$className}' must implement NodeInterface");
        }

        return $node;
    }

    /**
     * Create node from job type
     */
    protected function createNodeFromType(string $type, string $id = null, string $name = null, array $config = []): NodeInterface
    {
        $registry = $this->getJobRegistry();
        
        $className = $registry->findJob($type);
        if (!$className) {
            throw new \InvalidArgumentException("Job type '{$type}' not found. Available types: " . implode(', ', $registry->getJobNames()));
        }

        return $this->createNodeFromClass($className, $id, $name, $config);
    }

    /**
     * Get or create job registry
     */
    protected function getJobRegistry(): JobRegistry
    {
        if (self::$jobRegistry === null) {
            self::$jobRegistry = new JobRegistry();
        }
        return self::$jobRegistry;
    }

    /**
     * Set custom job registry
     */
    public static function setJobRegistry(JobRegistry $registry): void
    {
        self::$jobRegistry = $registry;
    }

    /**
     * Get available job types
     */
    public function getAvailableJobs(): array
    {
        return $this->getJobRegistry()->getAllJobs();
    }

    public function removeNode(string $nodeId): self
    {
        unset($this->nodes[$nodeId]);
        $this->connections = array_filter($this->connections, function ($connection) use ($nodeId) {
            return $connection['from'] !== $nodeId && $connection['to'] !== $nodeId;
        });
        return $this;
    }

    public function getNode(string $nodeId): ?NodeInterface
    {
        return $this->nodes[$nodeId] ?? null;
    }

    public function addConnection(string $fromNodeId, string $toNodeId, string $fromOutput = 'output', string $toInput = 'input'): self
    {
        if (!isset($this->nodes[$fromNodeId]) || !isset($this->nodes[$toNodeId])) {
            throw new \InvalidArgumentException("Both nodes must exist to create a connection");
        }

        $this->connections[] = [
            'from' => $fromNodeId,
            'to' => $toNodeId,
            'fromOutput' => $fromOutput,
            'toInput' => $toInput,
        ];

        return $this;
    }

    public function removeConnection(string $fromNodeId, string $toNodeId): self
    {
        $this->connections = array_filter($this->connections, function ($connection) use ($fromNodeId, $toNodeId) {
            return !($connection['from'] === $fromNodeId && $connection['to'] === $toNodeId);
        });
        return $this;
    }

    public function validate(): bool
    {
        foreach ($this->nodes as $node) {
            if (!$node->validate()) {
                return false;
            }
        }

        foreach ($this->connections as $connection) {
            if (!isset($this->nodes[$connection['from']]) || !isset($this->nodes[$connection['to']])) {
                return false;
            }
        }

        return true;
    }

    public function getStartNodes(): array
    {
        $fromNodes = array_column($this->connections, 'from');
        return array_filter($this->nodes, function ($nodeId) use ($fromNodes) {
            return !in_array($nodeId, $fromNodes);
        }, ARRAY_FILTER_USE_KEY);
    }

    public function getNextNodes(string $nodeId): array
    {
        $nextNodes = [];
        foreach ($this->connections as $connection) {
            if ($connection['from'] === $nodeId) {
                $nextNodes[] = $this->nodes[$connection['to']];
            }
        }
        return $nextNodes;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'nodes' => array_map(fn($node) => $node->toArray(), $this->nodes),
            'connections' => $this->connections,
        ];
    }

    public static function fromArray(array $data): self
    {
        $workflow = new self($data['id'], $data['name'], $data['description'] ?? '');
        
        // Nodes will be added via registry when creating from JSON
        // This is a basic structure - full implementation would need registry
        
        return $workflow;
    }
}