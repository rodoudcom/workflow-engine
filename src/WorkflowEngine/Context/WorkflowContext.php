<?php

namespace App\WorkflowEngine\Context;

class WorkflowContext
{
    protected array $data = [];
    protected array $variables = [];

    public function __construct(array $initialData = [])
    {
        $this->data = $initialData;
    }

    public function set(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key, $default = null)
    {
        return $this->getNestedValue($this->data, $key) ?? $default;
    }

    public function has(string $key): bool
    {
        return $this->getNestedValue($this->data, $key) !== null;
    }

    public function remove(string $key): self
    {
        $this->removeNestedValue($this->data, $key);
        return $this;
    }

    public function merge(array $data): self
    {
        $this->data = array_merge_recursive($this->data, $data);
        return $this;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function clear(): self
    {
        $this->data = [];
        return $this;
    }

    public function setVariable(string $name, $value): self
    {
        $this->variables[$name] = $value;
        return $this;
    }

    public function getVariable(string $name, $default = null)
    {
        return $this->variables[$name] ?? $default;
    }

    public function hasVariable(string $name): bool
    {
        return isset($this->variables[$name]);
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function processTemplate(string $template): string
    {
        $context = array_merge($this->data, $this->variables);
        
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($context) {
            $key = trim($matches[1]);
            return $this->getNestedValue($context, $key) ?? $matches[0];
        }, $template);
    }

    public function processTemplates(array $data): array
    {
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = $this->processTemplate($value);
            }
        });
        return $data;
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

    protected function removeNestedValue(array &$array, string $key): bool
    {
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        $current = &$array;
        
        foreach ($keys as $k) {
            if (!is_array($current) || !array_key_exists($k, $current)) {
                return false;
            }
            $current = &$current[$k];
        }
        
        if (is_array($current) && array_key_exists($lastKey, $current)) {
            unset($current[$lastKey]);
            return true;
        }
        
        return false;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'variables' => $this->variables,
        ];
    }

    public static function fromArray(array $data): self
    {
        $context = new self($data['data'] ?? []);
        $context->variables = $data['variables'] ?? [];
        return $context;
    }
}