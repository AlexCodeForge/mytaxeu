<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobLogCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $logId;
    public int $jobId;
    public string $level;
    public string $message;
    public array $metadata;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $logId,
        int $jobId,
        string $level,
        string $message,
        array $metadata = []
    ) {
        $this->logId = $logId;
        $this->jobId = $jobId;
        $this->level = $level;
        $this->message = $message;
        $this->metadata = $metadata;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to admin channel for log monitoring
        return [
            new PrivateChannel('admin.job-logs'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'log_id' => $this->logId,
            'job_id' => $this->jobId,
            'level' => $this->level,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'job-log-created';
    }
}
