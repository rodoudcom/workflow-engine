<?php

namespace App\WorkflowEngine\Config;

use App\WorkflowEngine\Interface\WorkflowInterface;
use App\WorkflowEngine\Core\Workflow;
use App\WorkflowEngine\Registry\NodeRegistry;

class WorkflowParser
{
    protected NodeRegistry $registry;

    public function __construct(NodeRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function parseJson(string $json): WorkflowInterface
    {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON: " . json_last_error_msg());
        }

        return $this->parseArray($data);
    }

    public function parseArray(array $data): WorkflowInterface
    {
        $this->validateWorkflowData($data);

        $workflow = new Workflow(
            $data['id'],
            $data['name'],
            $data['description'] ?? ''
        );

        // Parse nodes
        foreach ($data['nodes'] as $nodeData) {
            $node = $this->parseNode($nodeData);
            $workflow->addNode($node);
        }

        // Parse connections
        foreach ($data['connections'] ?? [] as $connectionData) {
            $workflow->addConnection(
                $connectionData['from'],
                $connectionData['to'],
                $connectionData['fromOutput'] ?? 'output',
                $connectionData['toInput'] ?? 'input'
            );
        }

        return $workflow;
    }

    public function parseFile(string $filePath): WorkflowInterface
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        
        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'json') {
            return $this->parseJson($content);
        }

        throw new \InvalidArgumentException("Unsupported file format. Only JSON files are supported.");
    }

    public function exportToJson(WorkflowInterface $workflow): string
    {
        return json_encode($workflow->toArray(), JSON_PRETTY_PRINT);
    }

    public function exportToFile(WorkflowInterface $workflow, string $filePath): void
    {
        $json = $this->exportToJson($workflow);
        
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($filePath, $json);
    }

    protected function parseNode(array $nodeData): \App\WorkflowEngine\Interface\NodeInterface
    {
        $type = $nodeData['type'];
        
        if (!$this->registry->has($type)) {
            throw new \InvalidArgumentException("Unknown node type: {$type}");
        }

        $node = $this->registry->createNode($type, $nodeData);
        
        if (!$node->validate()) {
            throw new \InvalidArgumentException("Invalid node configuration for: {$nodeData['id']}");
        }

        return $node;
    }

    protected function validateWorkflowData(array $data): void
    {
        $required = ['id', 'name', 'nodes'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!is_array($data['nodes'])) {
            throw new \InvalidArgumentException("Nodes must be an array");
        }

        // Validate each node
        foreach ($data['nodes'] as $node) {
            $this->validateNodeData($node);
        }

        // Validate connections if present
        if (isset($data['connections'])) {
            if (!is_array($data['connections'])) {
                throw new \InvalidArgumentException("Connections must be an array");
            }

            foreach ($data['connections'] as $connection) {
                $this->validateConnectionData($connection);
            }
        }
    }

    protected function validateNodeData(array $node): void
    {
        $required = ['id', 'name', 'type'];
        
        foreach ($required as $field) {
            if (!isset($node[$field])) {
                throw new \InvalidArgumentException("Missing required node field: {$field}");
            }
        }

        if (!is_string($node['id'])) {
            throw new \InvalidArgumentException("Node ID must be a string");
        }

        if (!is_string($node['name'])) {
            throw new \InvalidArgumentException("Node name must be a string");
        }

        if (!is_string($node['type'])) {
            throw new \InvalidArgumentException("Node type must be a string");
        }
    }

    protected function validateConnectionData(array $connection): void
    {
        $required = ['from', 'to'];
        
        foreach ($required as $field) {
            if (!isset($connection[$field])) {
                throw new \InvalidArgumentException("Missing required connection field: {$field}");
            }
        }

        if (!is_string($connection['from'])) {
            throw new \InvalidArgumentException("Connection 'from' must be a string");
        }

        if (!is_string($connection['to'])) {
            throw new \InvalidArgumentException("Connection 'to' must be a string");
        }
    }

    public function createTemplate(string $type): array
    {
        return match ($type) {
            'http' => [
                'id' => 'http_request',
                'name' => 'HTTP Request',
                'type' => 'http',
                'config' => [
                    'url' => 'https://api.example.com/data',
                    'method' => 'GET',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 30,
                    'stopWorkflowOnFail' => true,
                ],
            ],
            'database' => [
                'id' => 'db_query',
                'name' => 'Database Query',
                'type' => 'database',
                'config' => [
                    'operation' => 'select',
                    'query' => 'SELECT * FROM users WHERE active = 1',
                    'params' => [],
                    'connection' => [
                        'host' => 'localhost',
                        'port' => 3306,
                        'database' => 'myapp',
                        'username' => 'user',
                        'password' => 'password',
                        'charset' => 'utf8mb4',
                    ],
                    'stopWorkflowOnFail' => true,
                ],
            ],
            'transform' => [
                'id' => 'data_transform',
                'name' => 'Data Transform',
                'type' => 'transform',
                'config' => [
                    'operation' => 'map',
                    'mapping' => [
                        'id' => 'user_id',
                        'name' => 'full_name',
                        'email' => 'email_address',
                    ],
                    'stopWorkflowOnFail' => true,
                ],
            ],
            'code' => [
                'id' => 'custom_code',
                'name' => 'Custom Code',
                'type' => 'code',
                'config' => [
                    'code' => 'return $input;',
                    'language' => 'php',
                    'timeout' => 30,
                    'stopWorkflowOnFail' => true,
                ],
            ],
            default => throw new \InvalidArgumentException("Unknown template type: {$type}"),
        };
    }

    public function createWorkflowTemplate(string $name, string $description = ''): array
    {
        return [
            'id' => uniqid('workflow_', true),
            'name' => $name,
            'description' => $description,
            'nodes' => [],
            'connections' => [],
        ];
    }
}