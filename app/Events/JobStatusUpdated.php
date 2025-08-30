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

class JobStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $jobId;
    public string $status;
    public ?int $userId;
    public ?string $fileName;
    public string $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $jobId,
        string $status,
        ?int $userId = null,
        ?string $fileName = null
    ) {
        $this->jobId = $jobId;
        $this->status = $status;
        $this->userId = $userId;
        $this->fileName = $fileName;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to user-specific channel if user ID is available
        if ($this->userId) {
            $channels[] = new PrivateChannel("user.{$this->userId}.jobs");
        }

        // Broadcast to admin channel
        $channels[] = new PrivateChannel('admin.jobs');

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->jobId,
            'status' => $this->status,
            'user_id' => $this->userId,
            'file_name' => $this->fileName,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'job-status-updated';
    }
}
