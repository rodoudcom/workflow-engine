<?php

namespace App\WorkflowEngine\Interface;

interface ExecutionInterface
{
    public function getId(): string;
    
    public function getWorkflowId(): string;
    
    public function getStatus(): string;
    
    public function getContext(): array;
    
    public function getLogs(): array;
    
    public function getStartTime(): ?\DateTime;
    
    public function getEndTime(): ?\DateTime;
    
    public function getDuration(): ?float;
    
    public function isRunning(): bool;
    
    public function isCompleted(): bool;
    
    public function isFailed(): bool;
    
    public function addLog(string $nodeId, string $level, string $message, array $data = []): self;
    
    public function setContext(array $context): self;
    
    public function updateContext(string $key, $value): self;
    
    public function start(): self;
    
    public function complete(): self;
    
    public function fail(string $error): self;
    
    public function toArray(): array;
}