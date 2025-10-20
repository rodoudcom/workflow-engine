<?php

namespace App\WorkflowEngine\Interface;

interface NodeInterface
{
    public function getId(): string;
    
    public function getName(): string;
    
    public function getType(): string;
    
    public function getConfig(): array;
    
    public function execute(array $context, array $input = []): array;
    
    public function validate(): bool;
    
    public function getOutputSchema(): array;
    
    public function getInputSchema(): array;
    
    public function getDescription(): string;
    
    public function getCategory(): string;
    
    public function getIcon(): string;
    
    public function getStopWorkflowOnFail(): bool;
    
    public function setStopWorkflowOnFail(bool $stop): self;
    
    public function getExecutionMode(): string;
    
    public function setExecutionMode(string $mode): self;
}