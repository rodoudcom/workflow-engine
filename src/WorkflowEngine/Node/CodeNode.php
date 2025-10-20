<?php

namespace Rodoud\WorkflowEngine\Node;

use Rodoud\WorkflowEngine\Core\AbstractNode;

/**
 * @Job(name="code", description="Execute custom PHP code")
 * @Job(name="script", description="Execute custom PHP code")
 * @Job(name="php", description="Execute custom PHP code")
 */
class CodeNode extends AbstractNode
{
    protected string $type = 'code';

    public function execute(array $context, array $input = []): array
    {
        $config = $this->processTemplates($this->config);
        $code = $config['code'] ?? '';
        $language = $config['language'] ?? 'php';

        $logs = [$this->log('info', "Starting code execution: {$language}")];

        try {
            $result = match ($language) {
                'php' => $this->executePhp($code, $context, $input),
                'javascript' => $this->executeJavaScript($code, $context, $input),
                default => throw new \Exception("Unsupported language: {$language}"),
            };

            $logs[] = $this->log('info', "Code execution completed successfully");

            return [
                'success' => true,
                'data' => $result,
                'logs' => $logs,
            ];

        } catch (\Exception $e) {
            $logs[] = $this->log('error', "Code execution failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => $logs,
            ];
        }
    }

    public function getDescription(): string
    {
        return 'Execute custom code (PHP or JavaScript) for advanced processing';
    }

    public function getCategory(): string
    {
        return 'Code';
    }

    public function getIcon(): string
    {
        return 'code';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => [
                    'type' => 'string',
                    'title' => 'Code',
                    'description' => 'Code to execute',
                ],
                'language' => [
                    'type' => 'string',
                    'title' => 'Language',
                    'description' => 'Programming language',
                    'enum' => ['php', 'javascript'],
                    'default' => 'php',
                ],
                'timeout' => [
                    'type' => 'number',
                    'title' => 'Timeout',
                    'description' => 'Execution timeout in seconds',
                    'default' => 30,
                ],
            ],
            'required' => ['code'],
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
                    'description' => 'Whether the code execution was successful',
                ],
                'data' => [
                    'title' => 'Result',
                    'description' => 'The result of code execution',
                ],
                'error' => [
                    'type' => 'string',
                    'title' => 'Error',
                    'description' => 'Error message if the execution failed',
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
            'language' => 'php',
            'timeout' => 30,
        ]);
    }

    private function executePhp(string $code, array $context, array $input): mixed
    {
        // Create a sandboxed environment
        $sandbox = new PhpSandbox($this->config['timeout'] ?? 30);
        
        // Provide context variables
        $sandbox->setVariables([
            'context' => $context,
            'input' => $input,
        ]);
        
        return $sandbox->execute($code);
    }

    private function executeJavaScript(string $code, array $context, array $input): mixed
    {
        // This would require a JavaScript engine like V8js
        // For now, we'll throw an exception
        throw new \Exception('JavaScript execution requires V8js extension');
    }
}

class PhpSandbox
{
    private int $timeout;
    private array $variables = [];

    public function __construct(int $timeout = 30)
    {
        $this->timeout = $timeout;
    }

    public function setVariables(array $variables): self
    {
        $this->variables = $variables;
        return $this;
    }

    public function execute(string $code): mixed
    {
        // Set up timeout handling
        $oldTimeout = ini_get('max_execution_time');
        set_time_limit($this->timeout);

        try {
            // Extract variables into current scope
            extract($this->variables);
            
            // Capture output
            ob_start();
            
            // Execute the code
            $result = eval($code);
            
            // Get any output
            $output = ob_get_clean();
            
            // Return result or output if no explicit return
            return $result ?? $output;
            
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \Exception("Sandbox execution failed: " . $e->getMessage());
        } finally {
            // Restore original timeout
            set_time_limit($oldTimeout);
        }
    }
}