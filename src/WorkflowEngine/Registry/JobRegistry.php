<?php

namespace Rodoud\WorkflowEngine\Registry;

use Rodoud\WorkflowEngine\Interface\NodeInterface;
use Rodoud\WorkflowEngine\Interface\RegistryInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Job Registry with annotation support and flexible job mapping
 */
class JobRegistry implements RegistryInterface
{
    protected array $jobs = [];
    protected array $jobAliases = [];
    protected array $classCache = [];

    public function __construct()
    {
        $this->registerBuiltinJobs();
    }

    public function register(string $type, string $className): self
    {
        $this->jobs[$type] = $className;
        $this->scanJobAnnotations($className);
        return $this;
    }

    public function unregister(string $type): self
    {
        unset($this->jobs[$type]);
        unset($this->jobAliases[$type]);
        return $this;
    }

    public function get(string $type): ?string
    {
        return $this->jobs[$type] ?? $this->jobAliases[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->jobs[$type]) || isset($this->jobAliases[$type]);
    }

    public function getAll(): array
    {
        return array_merge($this->jobs, $this->jobAliases);
    }

    public function createNode(string $type, array $config): ?NodeInterface
    {
        $className = $this->get($type);
        if (!$className) {
            throw new \InvalidArgumentException("Job type '{$type}' not found");
        }

        if (!class_exists($className)) {
            throw new \ClassNotFoundException("Job class '{$className}' not found");
        }

        $node = new $className($config);
        
        if (!$node instanceof NodeInterface) {
            throw new \InvalidArgumentException("Job class '{$className}' must implement NodeInterface");
        }

        return $node;
    }

    /**
     * Get all available job names and aliases
     */
    public function getJobNames(): array
    {
        return array_keys(array_merge($this->jobs, $this->jobAliases));
    }

    /**
     * Get job information including description
     */
    public function getJobInfo(string $type): ?array
    {
        $className = $this->get($type);
        if (!$className) {
            return null;
        }

        if (!isset($this->classCache[$className])) {
            $this->classCache[$className] = $this->extractJobInfo($className);
        }

        return $this->classCache[$className];
    }

    /**
     * Get all jobs with their information
     */
    public function getAllJobs(): array
    {
        $jobs = [];
        $allTypes = array_merge($this->jobs, $this->jobAliases);
        
        foreach ($allTypes as $type => $className) {
            $jobs[$type] = $this->getJobInfo($type);
        }

        return $jobs;
    }

    /**
     * Register a job instance directly
     */
    public function registerJobInstance(string $name, NodeInterface $node): self
    {
        $this->jobs[$name] = get_class($node);
        return $this;
    }

    /**
     * Register job by class name (auto-detect type)
     */
    public function registerJobClass(string $className): self
    {
        if (!class_exists($className)) {
            throw new \ClassNotFoundException("Class '{$className}' not found");
        }

        $reflection = new ReflectionClass($className);
        if (!$reflection->implementsInterface(NodeInterface::class)) {
            throw new \InvalidArgumentException("Class '{$className}' must implement NodeInterface");
        }

        // Try to get the type from the class
        $instance = new $className([]);
        $type = $instance->getType() ?? strtolower($reflection->getShortName());
        
        $this->register($type, $className);
        return $this;
    }

    /**
     * Scan class for job annotations
     */
    protected function scanJobAnnotations(string $className): void
    {
        try {
            $reflection = new ReflectionClass($className);
            $docComment = $reflection->getDocComment();
            
            if ($docComment) {
                // Extract @Job annotations
                if (preg_match_all('/@Job\s*\(\s*name\s*=\s*["\']([^"\']+)["\'](?:,\s*description\s*=\s*["\']([^"\']+)["\'])?\s*\)/', $docComment, $matches)) {
                    foreach ($matches[1] as $index => $jobName) {
                        $this->jobAliases[$jobName] = $className;
                    }
                }
            }
        } catch (ReflectionException $e) {
            // Ignore reflection errors
        }
    }

    /**
     * Extract job information from class
     */
    protected function extractJobInfo(string $className): array
    {
        try {
            $reflection = new ReflectionClass($className);
            $docComment = $reflection->getDocComment();
            
            $info = [
                'class' => $className,
                'name' => $reflection->getShortName(),
                'description' => '',
                'category' => 'Unknown',
                'icon' => 'cog',
                'aliases' => [],
            ];

            // Extract @Job annotations for aliases and descriptions
            if ($docComment) {
                if (preg_match_all('/@Job\s*\(\s*name\s*=\s*["\']([^"\']+)["\'](?:,\s*description\s*=\s*["\']([^"\']+)["\'])?\s*\)/', $docComment, $matches)) {
                    foreach ($matches[1] as $index => $jobName) {
                        $info['aliases'][] = $jobName;
                        if (isset($matches[2][$index]) && $matches[2][$index]) {
                            $info['description'] = $matches[2][$index];
                        }
                    }
                }
            }

            // Try to create an instance to get more info
            try {
                $instance = new $className([]);
                if (method_exists($instance, 'getDescription')) {
                    $info['description'] = $instance->getDescription();
                }
                if (method_exists($instance, 'getCategory')) {
                    $info['category'] = $instance->getCategory();
                }
                if (method_exists($instance, 'getIcon')) {
                    $info['icon'] = $instance->getIcon();
                }
                if (method_exists($instance, 'getType')) {
                    $info['type'] = $instance->getType();
                }
            } catch (\Exception $e) {
                // Ignore instance creation errors
            }

            return $info;
        } catch (ReflectionException $e) {
            return [
                'class' => $className,
                'name' => basename(str_replace('\\', '/', $className)),
                'description' => '',
                'category' => 'Unknown',
                'icon' => 'cog',
                'aliases' => [],
            ];
        }
    }

    /**
     * Register built-in job types
     */
    protected function registerBuiltinJobs(): void
    {
        $this->register('http', \Rodoud\WorkflowEngine\Node\HttpNode::class);
        $this->register('database', \Rodoud\WorkflowEngine\Node\DatabaseNode::class);
        $this->register('transform', \Rodoud\WorkflowEngine\Node\TransformNode::class);
        $this->register('code', \Rodoud\WorkflowEngine\Node\CodeNode::class);
    }

    /**
     * Find job class by various criteria
     */
    public function findJob(string $search): ?string
    {
        // Direct match
        if ($this->has($search)) {
            return $this->get($search);
        }

        // Case-insensitive search
        $lowerSearch = strtolower($search);
        foreach ($this->getAll() as $type => $className) {
            if (strtolower($type) === $lowerSearch) {
                return $className;
            }
        }

        // Partial match
        foreach ($this->getAll() as $type => $className) {
            if (str_contains(strtolower($type), $lowerSearch)) {
                return $className;
            }
        }

        return null;
    }

    /**
     * Get jobs by category
     */
    public function getJobsByCategory(string $category): array
    {
        $jobs = [];
        foreach ($this->getAllJobs() as $type => $info) {
            if (strcasecmp($info['category'], $category) === 0) {
                $jobs[$type] = $info;
            }
        }
        return $jobs;
    }

    /**
     * Get all categories
     */
    public function getCategories(): array
    {
        $categories = [];
        foreach ($this->getAllJobs() as $type => $info) {
            $categories[] = $info['category'];
        }
        return array_unique($categories);
    }
}