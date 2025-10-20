<?php

namespace Rodoud\WorkflowEngine\Logger;

use Rodoud\WorkflowEngine\Interface\ExecutionInterface;
use Rodoud\WorkflowEngine\Interface\NodeInterface;

class WorkflowLogger
{
    protected array $logs = [];
    protected ?\Predis\Client $redis = null;
    protected array $redisConfig = [];
    protected string $logLevel = 'info';

    public function __construct(array $redisConfig = [], string $logLevel = 'info')
    {
        $this->redisConfig = $redisConfig;
        $this->logLevel = $logLevel;
        $this->initializeRedis();
    }

    public function log(string $level, string $message, array $context = [], ?string $executionId = null, ?string $nodeId = null): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $logEntry = [
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'execution_id' => $executionId,
            'node_id' => $nodeId,
        ];

        $this->logs[] = $logEntry;

        if ($this->redis) {
            $this->saveLogToRedis($logEntry);
        }
    }

    public function logNodeStart(NodeInterface $node, string $executionId): void
    {
        $this->log('info', "Node execution started: {$node->getName()}", [
            'node_type' => $node->getType(),
            'node_config' => $node->getConfig(),
        ], $executionId, $node->getId());
    }

    public function logNodeSuccess(NodeInterface $node, string $executionId, array $result): void
    {
        $this->log('info', "Node execution completed: {$node->getName()}", [
            'node_type' => $node->getType(),
            'execution_time' => $result['execution_time'] ?? null,
            'output_size' => isset($result['data']) ? strlen(json_encode($result['data'])) : 0,
        ], $executionId, $node->getId());
    }

    public function logNodeError(NodeInterface $node, string $executionId, \Exception $error): void
    {
        $this->log('error', "Node execution failed: {$node->getName()}", [
            'node_type' => $node->getType(),
            'error_message' => $error->getMessage(),
            'error_trace' => $error->getTraceAsString(),
        ], $executionId, $node->getId());
    }

    public function logWorkflowStart(string $workflowId, string $executionId, array $context): void
    {
        $this->log('info', "Workflow execution started", [
            'workflow_id' => $workflowId,
            'context_size' => count($context),
        ], $executionId);
    }

    public function logWorkflowComplete(string $workflowId, string $executionId, float $duration): void
    {
        $this->log('info', "Workflow execution completed", [
            'workflow_id' => $workflowId,
            'duration' => $duration,
        ], $executionId);
    }

    public function logWorkflowError(string $workflowId, string $executionId, string $error): void
    {
        $this->log('error', "Workflow execution failed", [
            'workflow_id' => $workflowId,
            'error' => $error,
        ], $executionId);
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function getLogsByExecution(string $executionId): array
    {
        return array_filter($this->logs, function ($log) use ($executionId) {
            return $log['execution_id'] === $executionId;
        });
    }

    public function getLogsByNode(string $executionId, string $nodeId): array
    {
        return array_filter($this->logs, function ($log) use ($executionId, $nodeId) {
            return $log['execution_id'] === $executionId && $log['node_id'] === $nodeId;
        });
    }

    public function getLogsByLevel(string $level): array
    {
        return array_filter($this->logs, function ($log) use ($level) {
            return $log['level'] === $level;
        });
    }

    public function clearLogs(): void
    {
        $this->logs = [];
    }

    public function exportLogs(string $format = 'json'): string
    {
        return match ($format) {
            'json' => json_encode($this->logs, JSON_PRETTY_PRINT),
            'csv' => $this->exportToCsv(),
            'txt' => $this->exportToText(),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    protected function shouldLog(string $level): bool
    {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3, 'critical' => 4];
        $currentLevel = $levels[$this->logLevel] ?? 1;
        $messageLevel = $levels[$level] ?? 1;
        
        return $messageLevel >= $currentLevel;
    }

    protected function initializeRedis(): void
    {
        if (empty($this->redisConfig)) {
            return;
        }

        try {
            $parameters = [
                'scheme' => $this->redisConfig['scheme'] ?? 'tcp',
                'host' => $this->redisConfig['host'] ?? '127.0.0.1',
                'port' => $this->redisConfig['port'] ?? 6379,
            ];

            $options = [];

            // Add password if provided
            if (!empty($this->redisConfig['password'])) {
                $parameters['password'] = $this->redisConfig['password'];
            }

            // Add database if provided
            if (isset($this->redisConfig['database'])) {
                $parameters['database'] = $this->redisConfig['database'];
            }

            // Add connection timeout
            if (isset($this->redisConfig['timeout'])) {
                $parameters['timeout'] = $this->redisConfig['timeout'];
            }

            // Add read/write timeout
            if (isset($this->redisConfig['read_write_timeout'])) {
                $parameters['read_write_timeout'] = $this->redisConfig['read_write_timeout'];
            }

            // Add prefix if provided
            if (!empty($this->redisConfig['prefix'])) {
                $options['prefix'] = $this->redisConfig['prefix'];
            }

            $this->redis = new \Predis\Client($parameters, $options);
            
            // Test connection
            $this->redis->connect();

        } catch (\Exception $e) {
            // Fail silently if Redis is not available
            $this->redis = null;
        }
    }

    protected function saveLogToRedis(array $logEntry): void
    {
        $key = "workflow_logs:" . date('Y-m-d');
        $this->redis->lpush($key, json_encode($logEntry));
        $this->redis->expire($key, 86400 * 30); // Keep for 30 days
    }

    protected function exportToCsv(): string
    {
        $csv = "timestamp,level,message,execution_id,node_id\n";
        
        foreach ($this->logs as $log) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s\n",
                $log['timestamp'],
                $log['level'],
                str_replace('"', '""', $log['message']),
                $log['execution_id'] ?? '',
                $log['node_id'] ?? ''
            );
        }
        
        return $csv;
    }

    protected function exportToText(): string
    {
        $text = "";
        
        foreach ($this->logs as $log) {
            $text .= sprintf(
                "[%s] %s: %s",
                $log['timestamp'],
                strtoupper($log['level']),
                $log['message']
            );
            
            if ($log['execution_id']) {
                $text .= " (Execution: {$log['execution_id']})";
            }
            
            if ($log['node_id']) {
                $text .= " (Node: {$log['node_id']})";
            }
            
            $text .= "\n";
        }
        
        return $text;
    }
}