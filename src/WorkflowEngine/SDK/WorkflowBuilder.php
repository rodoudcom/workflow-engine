<?php

namespace Rodoud\WorkflowEngine\SDK;

use Rodoud\WorkflowEngine\Interface\WorkflowInterface;
use Rodoud\WorkflowEngine\Interface\NodeInterface;
use Rodoud\WorkflowEngine\Core\Workflow;

class WorkflowBuilder
{
    protected ?WorkflowInterface $workflow = null;
    protected WorkflowEngine $engine;

    public function __construct(WorkflowEngine $engine)
    {
        $this->engine = $engine;
    }

    public function create(string $id, string $name, string $description = ''): self
    {
        $this->workflow = $this->engine->createWorkflow($id, $name, $description);
        return $this;
    }

    public function loadFromJson(string $json): self
    {
        $this->workflow = $this->engine->loadWorkflowFromJson($json);
        return $this;
    }

    public function loadFromFile(string $filePath): self
    {
        $this->workflow = $this->engine->loadWorkflowFromFile($filePath);
        return $this;
    }

    public function addHttpNode(string $id, string $name, array $config = []): self
    {
        $nodeConfig = array_merge($this->engine->createNodeTemplate('http')['config'], $config);
        $node = $this->engine->createNode('http', array_merge($nodeConfig, ['id' => $id, 'name' => $name]));
        $this->workflow->addNode($node);
        return $this;
    }

    public function addDatabaseNode(string $id, string $name, array $config = []): self
    {
        $nodeConfig = array_merge($this->engine->createNodeTemplate('database')['config'], $config);
        $node = $this->engine->createNode('database', array_merge($nodeConfig, ['id' => $id, 'name' => $name]));
        $this->workflow->addNode($node);
        return $this;
    }

    public function addTransformNode(string $id, string $name, array $config = []): self
    {
        $nodeConfig = array_merge($this->engine->createNodeTemplate('transform')['config'], $config);
        $node = $this->engine->createNode('transform', array_merge($nodeConfig, ['id' => $id, 'name' => $name]));
        $this->workflow->addNode($node);
        return $this;
    }

    public function addCodeNode(string $id, string $name, array $config = []): self
    {
        $nodeConfig = array_merge($this->engine->createNodeTemplate('code')['config'], $config);
        $node = $this->engine->createNode('code', array_merge($nodeConfig, ['id' => $id, 'name' => $name]));
        $this->workflow->addNode($node);
        return $this;
    }

    public function addAsyncHttpNode(string $id, string $name, array $config = []): self
    {
        return $this->addHttpNode($id, $name, array_merge($config, ['executionMode' => 'async']));
    }

    public function addAsyncDatabaseNode(string $id, string $name, array $config = []): self
    {
        return $this->addDatabaseNode($id, $name, array_merge($config, ['executionMode' => 'async']));
    }

    public function addAsyncTransformNode(string $id, string $name, array $config = []): self
    {
        return $this->addTransformNode($id, $name, array_merge($config, ['executionMode' => 'async']));
    }

    public function addAsyncCodeNode(string $id, string $name, array $config = []): self
    {
        return $this->addCodeNode($id, $name, array_merge($config, ['executionMode' => 'async']));
    }

    public function addNode(string $type, string $id, string $name, array $config = []): self
    {
        $node = $this->engine->createNode($type, array_merge($config, ['id' => $id, 'name' => $name]));
        $this->workflow->addNode($node);
        return $this;
    }

    /**
     * Add a job to the workflow - flexible method supporting multiple approaches
     * 
     * Usage examples:
     * ->addJob('http', 'fetch_api', 'Fetch API Data', ['url' => 'https://api.example.com'])
     * ->addJob('httpRequest', 'fetch_api', 'Fetch API Data', ['url' => 'https://api.example.com'])
     * ->addJob(HttpNode::class, 'fetch_api', 'Fetch API Data', ['url' => 'https://api.example.com'])
     * ->addJob(new HttpNode(['id' => 'fetch_api', 'name' => 'Fetch API Data', 'url' => 'https://api.example.com']))
     * ->addJob(['type' => 'http', 'id' => 'fetch_api', 'name' => 'Fetch API Data', 'config' => ['url' => 'https://api.example.com']])
     */
    public function addJob($jobTypeOrClassOrInstance, string $id = null, string $name = null, array $config = []): self
    {
        if (!$this->workflow) {
            throw new \Exception('No workflow created. Call create() or loadFromJson() first.');
        }

        $this->workflow->addJob($jobTypeOrClassOrInstance, $id, $name, $config);
        return $this;
    }

    /**
     * Add a job with async execution mode
     */
    public function addAsyncJob($jobTypeOrClassOrInstance, string $id = null, string $name = null, array $config = []): self
    {
        if (!$this->workflow) {
            throw new \Exception('No workflow created. Call create() or loadFromJson() first.');
        }

        $this->workflow->addAsyncJob($jobTypeOrClassOrInstance, $id, $name, $config);
        return $this;
    }

    /**
     * Get available job types
     */
    public function getAvailableJobs(): array
    {
        if (!$this->workflow) {
            throw new \Exception('No workflow created. Call create() or loadFromJson() first.');
        }

        return $this->workflow->getAvailableJobs();
    }

    public function connect(string $fromNodeId, string $toNodeId, string $fromOutput = 'output', string $toInput = 'input'): self
    {
        $this->workflow->addConnection($fromNodeId, $toNodeId, $fromOutput, $toInput);
        return $this;
    }

    public function execute(array $context = []): \Rodoud\WorkflowEngine\Interface\ExecutionInterface
    {
        if (!$this->workflow) {
            throw new \Exception('No workflow created. Call create() or loadFromJson() first.');
        }

        return $this->engine->executeWorkflow($this->workflow, $context);
    }

    public function executeAsync(array $context = []): mixed
    {
        if (!$this->workflow) {
            throw new \Exception('No workflow created. Call create() or loadFromJson() first.');
        }

        return $this->engine->executeWorkflowAsync($this->workflow, $context);
    }

    public function save(string $filePath): self
    {
        if (!$this->workflow) {
            throw new \Exception('No workflow created. Call create() or loadFromJson() first.');
        }

        $this->engine->saveWorkflow($this->workflow, $filePath);
        return $this;
    }

    public function validate(): bool
    {
        if (!$this->workflow) {
            throw new \Exception('No workflow created. Call create() or loadFromJson() first.');
        }

        return $this->engine->validateWorkflow($this->workflow);
    }

    public function getWorkflow(): WorkflowInterface
    {
        if (!$this->workflow) {
            throw new \Exception('No workflow created. Call create() or loadFromJson() first.');
        }

        return $this->workflow;
    }

    public function toJson(): string
    {
        if (!$this->workflow) {
            throw new \Exception('No workflow created. Call create() or loadFromJson() first.');
        }

        return json_encode($this->workflow->toArray(), JSON_PRETTY_PRINT);
    }

    public function toArray(): array
    {
        if (!$this->workflow) {
            throw new \Exception('No workflow created. Call create() or loadFromJson() first.');
        }

        return $this->workflow->toArray();
    }
}