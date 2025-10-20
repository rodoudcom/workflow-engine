<?php

namespace Rodoud\WorkflowEngine\Interface;

interface RegistryInterface
{
    public function register(string $type, string $className): self;
    
    public function unregister(string $type): self;
    
    public function get(string $type): ?string;
    
    public function has(string $type): bool;
    
    public function getAll(): array;
    
    public function createNode(string $type, array $config): ?NodeInterface;
}