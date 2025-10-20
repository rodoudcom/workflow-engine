<?php

namespace App\WorkflowEngine\Node;

use App\WorkflowEngine\Core\AbstractNode;

class TransformNode extends AbstractNode
{
    protected string $type = 'transform';

    public function execute(array $context, array $input = []): array
    {
        $config = $this->processTemplates($this->config);
        $operation = $config['operation'] ?? 'map';
        $data = $input['data'] ?? $context['data'] ?? [];

        $logs = [$this->log('info', "Starting transform operation: {$operation}")];

        try {
            $result = match ($operation) {
                'map' => $this->executeMap($data, $config),
                'filter' => $this->executeFilter($data, $config),
                'reduce' => $this->executeReduce($data, $config),
                'sort' => $this->executeSort($data, $config),
                'group' => $this->executeGroup($data, $config),
                'merge' => $this->executeMerge($data, $config),
                'split' => $this->executeSplit($data, $config),
                'custom' => $this->executeCustom($data, $config),
                default => throw new \Exception("Unsupported operation: {$operation}"),
            };

            $logs[] = $this->log('info', "Transform operation completed successfully");

            return [
                'success' => true,
                'data' => $result,
                'originalData' => $data,
                'logs' => $logs,
            ];

        } catch (\Exception $e) {
            $logs[] = $this->log('error', "Transform operation failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => $logs,
            ];
        }
    }

    public function getDescription(): string
    {
        return 'Transform and manipulate data with various operations';
    }

    public function getCategory(): string
    {
        return 'Transform';
    }

    public function getIcon(): string
    {
        return 'transform';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'title' => 'Operation',
                    'description' => 'Transform operation to perform',
                    'enum' => ['map', 'filter', 'reduce', 'sort', 'group', 'merge', 'split', 'custom'],
                    'default' => 'map',
                ],
                'mapping' => [
                    'type' => 'object',
                    'title' => 'Field Mapping',
                    'description' => 'Field mapping for map operation (key: new field name, value: source field or expression)',
                ],
                'condition' => [
                    'type' => 'string',
                    'title' => 'Filter Condition',
                    'description' => 'Filter condition (PHP expression)',
                ],
                'sortBy' => [
                    'type' => 'string',
                    'title' => 'Sort Field',
                    'description' => 'Field to sort by',
                ],
                'sortOrder' => [
                    'type' => 'string',
                    'title' => 'Sort Order',
                    'description' => 'Sort order',
                    'enum' => ['asc', 'desc'],
                    'default' => 'asc',
                ],
                'groupBy' => [
                    'type' => 'string',
                    'title' => 'Group By Field',
                    'description' => 'Field to group by',
                ],
                'customCode' => [
                    'type' => 'string',
                    'title' => 'Custom PHP Code',
                    'description' => 'Custom PHP code for transformation (return $result)',
                ],
            ],
            'required' => ['operation'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'success' => [
                    'type' => 'boolean',
                    'title' => 'Success',
                    'description' => 'Whether the transformation was successful',
                ],
                'data' => [
                    'title' => 'Transformed Data',
                    'description' => 'The transformed data',
                ],
                'originalData' => [
                    'title' => 'Original Data',
                    'description' => 'The original input data',
                ],
                'error' => [
                    'type' => 'string',
                    'title' => 'Error',
                    'description' => 'Error message if the transformation failed',
                ],
                'logs' => [
                    'type' => 'array',
                    'title' => 'Logs',
                    'description' => 'Execution logs',
                ],
            ],
        ];
    }

    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'operation' => 'map',
            'sortOrder' => 'asc',
        ]);
    }

    private function executeMap(array $data, array $config): array
    {
        if (!is_array($data)) {
            return $data;
        }

        $mapping = $config['mapping'] ?? [];
        
        if (empty($mapping)) {
            return $data;
        }

        return array_map(function ($item) use ($mapping) {
            $result = [];
            foreach ($mapping as $newField => $sourceField) {
                if (str_starts_with($sourceField, 'expr:')) {
                    $expression = substr($sourceField, 5);
                    $result[$newField] = $this->evaluateExpression($expression, $item);
                } else {
                    $result[$newField] = $this->getNestedValue($item, $sourceField);
                }
            }
            return $result;
        }, $data);
    }

    private function executeFilter(array $data, array $config): array
    {
        if (!is_array($data)) {
            return $data;
        }

        $condition = $config['condition'] ?? 'true';
        
        return array_filter($data, function ($item) use ($condition) {
            return $this->evaluateExpression($condition, $item);
        });
    }

    private function executeSort(array $data, array $config): array
    {
        if (!is_array($data)) {
            return $data;
        }

        $sortBy = $config['sortBy'] ?? null;
        $sortOrder = $config['sortOrder'] ?? 'asc';

        if (!$sortBy) {
            return $data;
        }

        usort($data, function ($a, $b) use ($sortBy, $sortOrder) {
            $aVal = $this->getNestedValue($a, $sortBy);
            $bVal = $this->getNestedValue($b, $sortBy);
            
            $comparison = $aVal <=> $bVal;
            return $sortOrder === 'desc' ? -$comparison : $comparison;
        });

        return $data;
    }

    private function executeGroup(array $data, array $config): array
    {
        if (!is_array($data)) {
            return $data;
        }

        $groupBy = $config['groupBy'] ?? null;
        
        if (!$groupBy) {
            return $data;
        }

        $groups = [];
        foreach ($data as $item) {
            $key = $this->getNestedValue($item, $groupBy);
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $item;
        }

        return $groups;
    }

    private function executeMerge(array $data, array $config): array
    {
        if (!is_array($data)) {
            return $data;
        }

        $mergeWith = $config['mergeWith'] ?? [];
        
        return array_merge($data, $mergeWith);
    }

    private function executeSplit(array $data, array $config): array
    {
        $separator = $config['separator'] ?? ',';
        
        if (is_string($data)) {
            return explode($separator, $data);
        }
        
        return $data;
    }

    private function executeReduce(array $data, array $config): array
    {
        if (!is_array($data)) {
            return $data;
        }

        $initial = $config['initial'] ?? null;
        $reducer = $config['reducer'] ?? function ($carry, $item) {
            return $carry;
        };

        return array_reduce($data, $reducer, $initial);
    }

    private function executeCustom(array $data, array $config): array
    {
        $code = $config['customCode'] ?? 'return $data;';
        
        // Create a safe evaluation context
        $result = null;
        try {
            $result = eval($code);
        } catch (\Throwable $e) {
            throw new \Exception("Custom code execution failed: " . $e->getMessage());
        }

        return $result;
    }

    private function evaluateExpression(string $expression, array $context)
    {
        // Extract variables from context
        extract($context);
        
        try {
            return eval("return {$expression};");
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getNestedValue(array $array, string $key)
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