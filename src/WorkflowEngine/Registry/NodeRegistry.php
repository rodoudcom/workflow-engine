<?php

namespace App\WorkflowEngine\Registry;

use App\WorkflowEngine\Interface\RegistryInterface;
use App\WorkflowEngine\Interface\NodeInterface;

class NodeRegistry implements RegistryInterface
{
    protected array $nodes = [];

    public function register(string $type, string $className): self
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Class {$className} does not exist");
        }

        if (!is_subclass_of($className, NodeInterface::class)) {
            throw new \InvalidArgumentException("Class {$className} must implement NodeInterface");
        }

        $this->nodes[$type] = $className;
        return $this;
    }

    public function unregister(string $type): self
    {
        unset($this->nodes[$type]);
        return $this;
    }

    public function get(string $type): ?string
    {
        return $this->nodes[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->nodes[$type]);
    }

    public function getAll(): array
    {
        return $this->nodes;
    }

    public function createNode(string $type, array $config): ?NodeInterface
    {
        if (!$this->has($type)) {
            throw new \InvalidArgumentException("Node type {$type} is not registered");
        }

        $className = $this->nodes[$type];
        
        if (!isset($config['id'])) {
            $config['id'] = uniqid('node_', true);
        }
        
        if (!isset($config['name'])) {
            $config['name'] = $type . ' Node';
        }

        return new $className($config['id'], $config['name'], $config);
    }

    public function getNodeTypes(): array
    {
        $types = [];
        foreach ($this->nodes as $type => $className) {
            $tempNode = new $className('temp', 'temp');
            $types[$type] = [
                'type' => $type,
                'className' => $className,
                'description' => $tempNode->getDescription(),
                'category' => $tempNode->getCategory(),
                'icon' => $tempNode->getIcon(),
                'inputSchema' => $tempNode->getInputSchema(),
                'outputSchema' => $tempNode->getOutputSchema(),
            ];
        }
        return $types;
    }
}