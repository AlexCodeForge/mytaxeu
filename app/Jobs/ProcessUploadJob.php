<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uploadId;
    
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $upload = Upload::find($this->uploadId);

        if (! $upload) {
            Log::error('ProcessUploadJob: Upload not found', ['upload_id' => $this->uploadId]);
            return;
        }

        try {
            // Set status to processing
            $this->updateStatus($upload, Upload::STATUS_PROCESSING);

            Log::info('ProcessUploadJob: Started processing', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'filename' => $upload->original_name,
            ]);

            // Verify file exists
            if (! Storage::disk($upload->disk)->exists($upload->path)) {
                throw new \Exception('Upload file not found in storage');
            }

            // Get file content
            $content = Storage::disk($upload->disk)->get($upload->path);
            if (empty($content)) {
                throw new \Exception('Upload file is empty');
            }

            // Process CSV (placeholder implementation)
            $this->processCsvContent($upload, $content);

            // Update row count if not set
            if (! $upload->rows_count) {
                $rowCount = substr_count($content, "\n") + 1;
                $upload->update(['rows_count' => $rowCount]);
            }

            // Simulate processing time (remove in production)
            sleep(2);

            // Mark as completed
            $this->updateStatus($upload, Upload::STATUS_COMPLETED);

            Log::info('ProcessUploadJob: Processing completed successfully', [
                'upload_id' => $upload->id,
                'rows_processed' => $upload->rows_count,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessUploadJob: Processing failed', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed
            $upload->update([
                'status' => Upload::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $upload = Upload::find($this->uploadId);

        if ($upload) {
            $upload->update([
                'status' => Upload::STATUS_FAILED,
                'failure_reason' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('ProcessUploadJob: Job failed permanently', [
                'upload_id' => $upload->id,
                'error' => $exception->getMessage(),
                'attempts' => $this->attempts(),
            ]);
        }
    }

    /**
     * Update upload status.
     */
    private function updateStatus(Upload $upload, string $status): void
    {
        $upload->update(['status' => $status]);

        Log::info('ProcessUploadJob: Status updated', [
            'upload_id' => $upload->id,
            'new_status' => $status,
        ]);
    }

    /**
     * Process CSV content (placeholder implementation).
     */
    private function processCsvContent(Upload $upload, string $content): void
    {
        // Placeholder for actual CSV processing logic
        // In a real implementation, this would:
        // 1. Parse CSV content
        // 2. Validate data structure
        // 3. Transform data according to business rules
        // 4. Generate output CSV or perform other operations
        // 5. Store results

        $lines = explode("\n", $content);
        $processedLines = 0;

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Simulate processing each line
            // In production: parse CSV, validate, transform data
            $processedLines++;

            // For demonstration: log every 100th line processed
            if ($processedLines % 100 === 0) {
                Log::info('ProcessUploadJob: Progress update', [
                    'upload_id' => $upload->id,
                    'lines_processed' => $processedLines,
                ]);
            }
        }

        Log::info('ProcessUploadJob: CSV processing completed', [
            'upload_id' => $upload->id,
            'total_lines_processed' => $processedLines,
        ]);
    }
}
