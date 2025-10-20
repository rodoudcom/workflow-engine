<?php

namespace App\WorkflowEngine\Core;

use App\WorkflowEngine\Interface\ExecutionInterface;

class Execution implements ExecutionInterface
{
    protected string $id;
    protected string $workflowId;
    protected string $status = 'pending';
    protected array $context = [];
    protected array $logs = [];
    protected ?\DateTime $startTime = null;
    protected ?\DateTime $endTime = null;

    public function __construct(string $id, string $workflowId, array $context = [])
    {
        $this->id = $id;
        $this->workflowId = $workflowId;
        $this->context = $context;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function getStartTime(): ?\DateTime
    {
        return $this->startTime;
    }

    public function getEndTime(): ?\DateTime
    {
        return $this->endTime;
    }

    public function getDuration(): ?float
    {
        if ($this->startTime && $this->endTime) {
            return $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
        }
        return null;
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function addLog(string $nodeId, string $level, string $message, array $data = []): self
    {
        if (!isset($this->logs[$nodeId])) {
            $this->logs[$nodeId] = [];
        }

        $this->logs[$nodeId][] = [
            'level' => $level,
            'message' => $message,
            'data' => $data,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s.u'),
        ];

        return $this;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function updateContext(string $key, $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    public function start(): self
    {
        $this->status = 'running';
        $this->startTime = new \DateTime();
        return $this;
    }

    public function complete(): self
    {
        $this->status = 'completed';
        $this->endTime = new \DateTime();
        return $this;
    }

    public function fail(string $error): self
    {
        $this->status = 'failed';
        $this->endTime = new \DateTime();
        $this->context['error'] = $error;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workflowId' => $this->workflowId,
            'status' => $this->status,
            'context' => $this->context,
            'logs' => $this->logs,
            'startTime' => $this->startTime?->format('Y-m-d H:i:s.u'),
            'endTime' => $this->endTime?->format('Y-m-d H:i:s.u'),
            'duration' => $this->getDuration(),
        ];
    }

    public static function fromArray(array $data): self
    {
        $execution = new self($data['id'], $data['workflowId'], $data['context'] ?? []);
        $execution->status = $data['status'] ?? 'pending';
        $execution->logs = $data['logs'] ?? [];
        
        if (isset($data['startTime'])) {
            $execution->startTime = \DateTime::createFromFormat('Y-m-d H:i:s.u', $data['startTime']);
        }
        
        if (isset($data['endTime'])) {
            $execution->endTime = \DateTime::createFromFormat('Y-m-d H:i:s.u', $data['endTime']);
        }

        return $execution;
    }
}