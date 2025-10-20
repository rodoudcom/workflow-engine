<?php

namespace Rodoud\WorkflowEngine\Core;

use Rodoud\WorkflowEngine\Interface\NodeInterface;

abstract class AbstractNode implements NodeInterface
{
    protected string $id;
    protected string $name;
    protected string $type;
    protected array $config;
    protected bool $stopWorkflowOnFail = true;
    protected string $executionMode = 'sync';

    public function __construct(string $id, string $name, array $config = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = static::class;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getStopWorkflowOnFail(): bool
    {
        return $this->config['stopWorkflowOnFail'] ?? $this->stopWorkflowOnFail;
    }

    public function setStopWorkflowOnFail(bool $stop): self
    {
        $this->config['stopWorkflowOnFail'] = $stop;
        $this->stopWorkflowOnFail = $stop;
        return $this;
    }

    public function getExecutionMode(): string
    {
        return $this->config['executionMode'] ?? $this->executionMode;
    }

    public function setExecutionMode(string $mode): self
    {
        if (!in_array($mode, ['sync', 'async'])) {
            throw new \InvalidArgumentException("Execution mode must be 'sync' or 'async'");
        }
        
        $this->config['executionMode'] = $mode;
        $this->executionMode = $mode;
        return $this;
    }

    public function validate(): bool
    {
        return !empty($this->id) && !empty($this->name);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'config' => $this->config,
            'description' => $this->getDescription(),
            'category' => $this->getCategory(),
            'icon' => $this->getIcon(),
            'inputSchema' => $this->getInputSchema(),
            'outputSchema' => $this->getOutputSchema(),
        ];
    }

    protected function getDefaultConfig(): array
    {
        return [
            'stopWorkflowOnFail' => $this->stopWorkflowOnFail,
            'executionMode' => $this->executionMode,
        ];
    }

    protected function log(string $level, string $message, array $data = []): array
    {
        return [
            'level' => $level,
            'message' => $message,
            'data' => $data,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];
    }

    protected function processVariables(string $text, array $context): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($context) {
            $key = trim($matches[1]);
            return $this->getNestedValue($context, $key) ?? $matches[0];
        }, $text);
    }

    protected function getNestedValue(array $array, string $key)
    {
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}