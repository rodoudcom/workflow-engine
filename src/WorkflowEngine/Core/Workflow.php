<?php

namespace App\WorkflowEngine\Core;

use App\WorkflowEngine\Interface\WorkflowInterface;
use App\WorkflowEngine\Interface\NodeInterface;

class Workflow implements WorkflowInterface
{
    protected string $id;
    protected string $name;
    protected string $description;
    protected array $nodes = [];
    protected array $connections = [];

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