<?php

namespace Rodoud\WorkflowEngine\SDK;

use Rodoud\WorkflowEngine\Interface\WorkflowInterface;
use Rodoud\WorkflowEngine\Interface\ExecutionInterface;
use Rodoud\WorkflowEngine\Interface\NodeInterface;
use Rodoud\WorkflowEngine\Core\Workflow;
use Rodoud\WorkflowEngine\Registry\NodeRegistry;
use Rodoud\WorkflowEngine\Execution\WorkflowExecutor;
use Rodoud\WorkflowEngine\Execution\AsyncWorkflowExecutor;
use Rodoud\WorkflowEngine\Config\WorkflowParser;
use Rodoud\WorkflowEngine\Logger\WorkflowLogger;
use Rodoud\WorkflowEngine\Context\WorkflowContext;

class WorkflowEngine
{
    protected NodeRegistry $registry;
    protected WorkflowExecutor $executor;
    protected WorkflowParser $parser;
    protected WorkflowLogger $logger;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->registry = new NodeRegistry();
        $this->executor = new WorkflowExecutor(
            $this->config['redis'] ?? [],
            $this->config['async'] ?? false
        );
        $this->parser = new WorkflowParser($this->registry);
        $this->logger = new WorkflowLogger(
            $this->config['redis'] ?? [],
            $this->config['log_level'] ?? 'info'
        );

        $this->registerBuiltinNodes();
    }

    public function createWorkflow(string $id, string $name, string $description = ''): WorkflowInterface
    {
        return new Workflow($id, $name, $description);
    }

    public function loadWorkflowFromJson(string $json): WorkflowInterface
    {
        return $this->parser->parseJson($json);
    }

    public function loadWorkflowFromFile(string $filePath): WorkflowInterface
    {
        return $this->parser->parseFile($filePath);
    }

    public function executeWorkflow(WorkflowInterface $workflow, array $context = []): ExecutionInterface
    {
        $this->logger->logWorkflowStart($workflow->getId(), '', $context);
        
        // Check if workflow has mixed execution modes
        $hasMixedExecution = $this->hasMixedExecution($workflow);
        
        if ($hasMixedExecution) {
            $executor = new MixedWorkflowExecutor(
                $this->config['redis'] ?? [],
                $this->config['max_workers'] ?? 4
            );
        } else {
            $executor = $this->executor;
        }
        
        $execution = $executor->execute($workflow, $context);
        
        if ($execution->isCompleted()) {
            $this->logger->logWorkflowComplete(
                $workflow->getId(),
                $execution->getId(),
                $execution->getDuration() ?? 0
            );
        } elseif ($execution->isFailed()) {
            $this->logger->logWorkflowError(
                $workflow->getId(),
                $execution->getId(),
                $execution->getContext()['error'] ?? 'Unknown error'
            );
        }

        return $execution;
    }

    protected function hasMixedExecution(WorkflowInterface $workflow): bool
    {
        $hasAsync = false;
        $hasSync = false;
        
        foreach ($workflow->getNodes() as $node) {
            if ($node->getExecutionMode() === 'async') {
                $hasAsync = true;
            } else {
                $hasSync = true;
            }
            
            if ($hasAsync && $hasSync) {
                return true;
            }
        }
        
        return false;
    }

    public function executeWorkflowAsync(WorkflowInterface $workflow, array $context = []): mixed
    {
        if (!$this->config['async']) {
            throw new \Exception('Async execution is not enabled');
        }

        $asyncExecutor = new AsyncWorkflowExecutor(
            $this->config['redis'] ?? [],
            $this->config['max_workers'] ?? 4
        );

        return $asyncExecutor->executeAsync($workflow, $context);
    }

    public function registerNodeType(string $type, string $className): self
    {
        $this->registry->register($type, $className);
        return $this;
    }

    public function getNodeTypes(): array
    {
        return $this->registry->getNodeTypes();
    }

    public function createNode(string $type, array $config): NodeInterface
    {
        return $this->registry->createNode($type, $config);
    }

    public function getExecution(string $executionId): ?ExecutionInterface
    {
        return $this->executor->getExecution($executionId);
    }

    public function getRunningExecutions(): array
    {
        return $this->executor->getRunningExecutions();
    }

    public function getWorkflowHistory(string $workflowId): array
    {
        return $this->executor->getWorkflowHistory($workflowId);
    }

    public function cancelExecution(string $executionId): bool
    {
        return $this->executor->cancelExecution($executionId);
    }

    public function getLogs(): array
    {
        return $this->logger->getLogs();
    }

    public function exportLogs(string $format = 'json'): string
    {
        return $this->logger->exportLogs($format);
    }

    public function saveWorkflow(WorkflowInterface $workflow, string $filePath): void
    {
        $this->parser->exportToFile($workflow, $filePath);
    }

    public function createNodeTemplate(string $type): array
    {
        return $this->parser->createTemplate($type);
    }

    public function createWorkflowTemplate(string $name, string $description = ''): array
    {
        return $this->parser->createWorkflowTemplate($name, $description);
    }

    public function validateWorkflow(WorkflowInterface $workflow): bool
    {
        return $workflow->validate();
    }

    public function getWorkflowSchema(): array
    {
        return [
            'workflow' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'nodes' => ['type' => 'array'],
                    'connections' => ['type' => 'array'],
                ],
                'required' => ['id', 'name', 'nodes'],
            ],
            'node_types' => $this->getNodeTypes(),
        ];
    }

    protected function registerBuiltinNodes(): void
    {
        $this->registry->register('http', \Rodoud\WorkflowEngine\Node\HttpNode::class);
        $this->registry->register('database', \Rodoud\WorkflowEngine\Node\DatabaseNode::class);
        $this->registry->register('transform', \Rodoud\WorkflowEngine\Node\TransformNode::class);
        $this->registry->register('code', \Rodoud\WorkflowEngine\Node\CodeNode::class);
    }

    protected function getDefaultConfig(): array
    {
        return [
            'async' => false,
            'max_workers' => 4,
            'log_level' => 'info',
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'password' => null,
                'database' => 0,
            ],
        ];
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function enableAsync(int $maxWorkers = 4): self
    {
        $this->config['async'] = true;
        $this->config['max_workers'] = $maxWorkers;
        return $this;
    }

    public function disableAsync(): self
    {
        $this->config['async'] = false;
        return $this;
    }

    public function setLogLevel(string $level): self
    {
        $this->config['log_level'] = $level;
        $this->logger = new WorkflowLogger(
            $this->config['redis'] ?? [],
            $level
        );
        return $this;
    }

    public function setRedisConfig(array $redisConfig): self
    {
        $this->config['redis'] = $redisConfig;
        $this->executor = new WorkflowExecutor($redisConfig, $this->config['async']);
        $this->logger = new WorkflowLogger($redisConfig, $this->config['log_level']);
        return $this;
    }
}