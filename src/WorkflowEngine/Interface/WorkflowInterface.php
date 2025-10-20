<?php

namespace App\WorkflowEngine\Interface;

interface WorkflowInterface
{
    public function getId(): string;
    
    public function getName(): string;
    
    public function getDescription(): string;
    
    public function getNodes(): array;
    
    public function getConnections(): array;
    
    public function addNode(NodeInterface $node): self;
    
    public function removeNode(string $nodeId): self;
    
    public function getNode(string $nodeId): ?NodeInterface;
    
    public function addConnection(string $fromNodeId, string $toNodeId, string $fromOutput = 'output', string $toInput = 'input'): self;
    
    public function removeConnection(string $fromNodeId, string $toNodeId): self;
    
    public function validate(): bool;
    
    public function toArray(): array;
    
    public static function fromArray(array $data): self;
}