<?php

namespace Rodoud\WorkflowEngine\Node;

use Rodoud\WorkflowEngine\Core\AbstractNode;

class DatabaseNode extends AbstractNode
{
    protected string $type = 'database';
    protected ?\PDO $connection = null;

    public function execute(array $context, array $input = []): array
    {
        $config = $this->processTemplates($this->config);
        $operation = $config['operation'] ?? 'select';
        $query = $config['query'] ?? '';
        $params = $config['params'] ?? [];

        $logs = [$this->log('info', "Starting database operation: {$operation}")];

        try {
            $this->connect($config);
            
            switch ($operation) {
                case 'select':
                    $result = $this->executeSelect($query, $params);
                    break;
                case 'insert':
                    $result = $this->executeInsert($query, $params);
                    break;
                case 'update':
                    $result = $this->executeUpdate($query, $params);
                    break;
                case 'delete':
                    $result = $this->executeDelete($query, $params);
                    break;
                default:
                    throw new \Exception("Unsupported operation: {$operation}");
            }

            $logs[] = $this->log('info', "Database operation completed successfully");

            return [
                'success' => true,
                'data' => $result['data'],
                'affectedRows' => $result['affectedRows'] ?? 0,
                'lastInsertId' => $result['lastInsertId'] ?? null,
                'logs' => $logs,
            ];

        } catch (\Exception $e) {
            $logs[] = $this->log('error', "Database operation failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => $logs,
            ];
        } finally {
            $this->disconnect();
        }
    }

    public function getDescription(): string
    {
        return 'Execute database operations (SELECT, INSERT, UPDATE, DELETE)';
    }

    public function getCategory(): string
    {
        return 'Database';
    }

    public function getIcon(): string
    {
        return 'database';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'title' => 'Operation',
                    'description' => 'Database operation to perform',
                    'enum' => ['select', 'insert', 'update', 'delete'],
                    'default' => 'select',
                ],
                'query' => [
                    'type' => 'string',
                    'title' => 'SQL Query',
                    'description' => 'SQL query to execute',
                ],
                'params' => [
                    'type' => 'array',
                    'title' => 'Parameters',
                    'description' => 'Query parameters for prepared statements',
                ],
                'connection' => [
                    'type' => 'object',
                    'title' => 'Database Connection',
                    'description' => 'Database connection parameters',
                    'properties' => [
                        'host' => ['type' => 'string'],
                        'port' => ['type' => 'number'],
                        'database' => ['type' => 'string'],
                        'username' => ['type' => 'string'],
                        'password' => ['type' => 'string'],
                        'charset' => ['type' => 'string', 'default' => 'utf8mb4'],
                    ],
                    'required' => ['host', 'database', 'username', 'password'],
                ],
            ],
            'required' => ['operation', 'query'],
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
                    'description' => 'Whether the operation was successful',
                ],
                'data' => [
                    'title' => 'Result Data',
                    'description' => 'Query result data',
                ],
                'affectedRows' => [
                    'type' => 'number',
                    'title' => 'Affected Rows',
                    'description' => 'Number of affected rows',
                ],
                'lastInsertId' => [
                    'title' => 'Last Insert ID',
                    'description' => 'Last inserted ID (for INSERT operations)',
                ],
                'error' => [
                    'type' => 'string',
                    'title' => 'Error',
                    'description' => 'Error message if the operation failed',
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
            'operation' => 'select',
            'params' => [],
        ]);
    }

    private function connect(array $config): void
    {
        if (!isset($config['connection'])) {
            throw new \Exception('Database connection configuration is required');
        }

        $conn = $config['connection'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $conn['host'],
            $conn['port'] ?? 3306,
            $conn['database'],
            $conn['charset'] ?? 'utf8mb4'
        );

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->connection = new \PDO($dsn, $conn['username'], $conn['password'], $options);
    }

    private function disconnect(): void
    {
        $this->connection = null;
    }

    private function executeSelect(string $query, array $params): array
    {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        
        return [
            'data' => $stmt->fetchAll(),
            'affectedRows' => $stmt->rowCount(),
        ];
    }

    private function executeInsert(string $query, array $params): array
    {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        
        return [
            'data' => ['inserted' => true],
            'affectedRows' => $stmt->rowCount(),
            'lastInsertId' => $this->connection->lastInsertId(),
        ];
    }

    private function executeUpdate(string $query, array $params): array
    {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        
        return [
            'data' => ['updated' => true],
            'affectedRows' => $stmt->rowCount(),
        ];
    }

    private function executeDelete(string $query, array $params): array
    {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        
        return [
            'data' => ['deleted' => true],
            'affectedRows' => $stmt->rowCount(),
        ];
    }
}