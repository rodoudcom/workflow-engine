<?php

namespace Rodoud\WorkflowEngine\Node;

use Rodoud\WorkflowEngine\Core\AbstractNode;

/**
 * @Job(name="http", description="Make HTTP requests to external APIs")
 * @Job(name="httpRequest", description="Make HTTP requests to external APIs")
 * @Job(name="api", description="Make HTTP requests to external APIs")
 */
class HttpNode extends AbstractNode
{
    protected string $type = 'http';

    public function execute(array $context, array $input = []): array
    {
        $config = $this->processTemplates($this->config);
        $url = $config['url'] ?? '';
        $method = strtoupper($config['method'] ?? 'GET');
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? null;
        $timeout = $config['timeout'] ?? 30;

        $logs = [$this->log('info', "Starting HTTP request: {$method} {$url}")];

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
            ]);

            if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception("HTTP request failed: {$error}");
            }

            $decodedResponse = json_decode($response, true) ?? $response;

            $logs[] = $this->log('info', "HTTP request completed", [
                'httpCode' => $httpCode,
                'responseSize' => strlen($response),
            ]);

            return [
                'success' => true,
                'data' => $decodedResponse,
                'httpCode' => $httpCode,
                'headers' => $headers,
                'logs' => $logs,
            ];

        } catch (\Exception $e) {
            $logs[] = $this->log('error', "HTTP request failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => $logs,
            ];
        }
    }

    public function getDescription(): string
    {
        return 'Make HTTP requests to external APIs or web services';
    }

    public function getCategory(): string
    {
        return 'Communication';
    }

    public function getIcon(): string
    {
        return 'globe';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'title' => 'URL',
                    'description' => 'The URL to make the request to',
                    'format' => 'uri',
                ],
                'method' => [
                    'type' => 'string',
                    'title' => 'Method',
                    'description' => 'HTTP method to use',
                    'enum' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
                    'default' => 'GET',
                ],
                'headers' => [
                    'type' => 'object',
                    'title' => 'Headers',
                    'description' => 'HTTP headers to send with the request',
                ],
                'body' => [
                    'title' => 'Body',
                    'description' => 'Request body (for POST, PUT, PATCH)',
                ],
                'timeout' => [
                    'type' => 'number',
                    'title' => 'Timeout',
                    'description' => 'Request timeout in seconds',
                    'default' => 30,
                ],
            ],
            'required' => ['url'],
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
                    'description' => 'Whether the request was successful',
                ],
                'data' => [
                    'title' => 'Response Data',
                    'description' => 'The response data from the HTTP request',
                ],
                'httpCode' => [
                    'type' => 'number',
                    'title' => 'HTTP Status Code',
                    'description' => 'The HTTP status code returned',
                ],
                'error' => [
                    'type' => 'string',
                    'title' => 'Error',
                    'description' => 'Error message if the request failed',
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
            'method' => 'GET',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = "{$key}: {$value}";
        }
        return $formatted;
    }
}