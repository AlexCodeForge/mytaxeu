<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Upload;
use App\Models\UploadMetric;
use App\Services\CreditService;
use App\Services\CsvTransformer;
use App\Services\StreamingCsvTransformer;
use App\Services\JobStatusService;
use App\Services\NotificationService;
use App\Services\UsageMeteringService;
use Illuminate\Support\Facades\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ProcessUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uploadId;
    public ?int $uploadMetricId = null;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 600; // 10 minutes for large files

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = true;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;

        // Set queue priority based on file size
        $upload = Upload::find($uploadId);
        if ($upload) {
            $fileSizeMB = $upload->size_bytes / 1024 / 1024;

            if ($fileSizeMB > 5) {
                // Large files go to slow queue
                $this->onQueue('slow');
            } elseif ($fileSizeMB < 1) {
                // Small files get priority
                $this->onQueue('high-priority');
            } else {
                // Medium files use default queue
                $this->onQueue('default');
            }
        }
    }

    /**
     * Execute the job.
     */
    public function handle(CsvTransformer $csvTransformer, JobStatusService $jobStatusService, NotificationService $notificationService, UsageMeteringService $usageMeteringService): void
    {
        // Set memory limit for this job
        ini_set('memory_limit', '1024M');

        // Track initial memory usage
        $initialMemory = memory_get_usage(true);

        $upload = Upload::find($this->uploadId);

        if (! $upload) {
            Log::error('ProcessUploadJob: Upload not found', ['upload_id' => $this->uploadId]);
            return;
        }

        // Create UploadMetric record to track processing details
        $uploadMetric = UploadMetric::create([
            'user_id' => $upload->user_id,
            'upload_id' => $upload->id,
            'file_name' => $upload->original_name,
            'file_size_bytes' => $upload->size_bytes,
            'line_count' => $upload->csv_line_count ?? 0,
            'processing_started_at' => now(),
            'status' => UploadMetric::STATUS_PROCESSING,
        ]);

        // Store uploadMetric ID for use in failed() method if needed
        $this->uploadMetricId = $uploadMetric->id;

        // Log memory usage
        Log::info('ProcessUploadJob: Starting with memory usage', [
            'upload_id' => $upload->id,
            'initial_memory_mb' => round($initialMemory / 1024 / 1024, 2),
            'memory_limit' => ini_get('memory_limit'),
        ]);

        // Initialize job metadata in the jobs table
        $this->initializeJobMetadata($upload, $jobStatusService);

        try {
            // Set status to processing in both upload and jobs table
            $this->updateStatus($upload, Upload::STATUS_PROCESSING);

            // Skip job status tracking for Redis queues
            if (!$this->shouldSkipJobStatusTracking()) {
                $jobStatusService->updateJobStatus(
                    jobId: (int) $this->job->getJobId(),
                    status: JobStatusService::STATUS_PROCESSING,
                    userId: $upload->user_id,
                    fileName: $upload->original_name
                );
            }

            // Log job activity (skip for Redis queues)
            if (!$this->shouldSkipJobStatusTracking()) {
                $jobStatusService->logJobActivity(
                    jobId: (int) $this->job->getJobId(),
                    level: JobStatusService::LOG_LEVEL_INFO,
                    message: 'Started processing CSV file',
                metadata: [
                    'upload_id' => $upload->id,
                    'user_id' => $upload->user_id,
                    'filename' => $upload->original_name,
                    'file_size' => $upload->size_bytes,
                ]
            );
            }

            Log::info('ProcessUploadJob: Started processing', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'filename' => $upload->original_name,
                'job_id' => $this->job->getJobId(),
            ]);

                        // Verify file exists
            if (! Storage::disk($upload->disk)->exists($upload->path)) {
                Log::error('Upload file not found in storage', [
                    'upload_id' => $upload->id,
                    'path' => $upload->path,
                    'disk' => $upload->disk
                ]);
                throw new \Exception('Upload file not found in storage');
            }

            // Check file size instead of loading entire content
            $fileSize = Storage::disk($upload->disk)->size($upload->path);
            if ($fileSize === 0) {
                throw new \Exception('Upload file is empty');
            }

            // Log validation step (skip for Redis queues)
            if (!$this->shouldSkipJobStatusTracking()) {
                $jobStatusService->logJobActivity(
                    jobId: (int) $this->job->getJobId(),
                    level: JobStatusService::LOG_LEVEL_INFO,
                    message: 'File validation completed successfully',
                    metadata: [
                        'file_size' => $upload->size_bytes,
                        'file_exists' => true,
                    ]
                );
            }

            // Choose transformer based on file size for optimal performance
            $fileSize = Storage::disk($upload->disk)->size($upload->path);
            $fileSizeMB = round($fileSize / 1024 / 1024, 2);

            if ($fileSizeMB > 5) {
                // Use streaming transformer for files >5MB (optimized for memory efficiency)
                $streamingTransformer = app(StreamingCsvTransformer::class);
                $this->processCsvTransformationStreaming($upload, $streamingTransformer, $jobStatusService);

                Log::info('ProcessUploadJob: Used streaming transformer for large file', [
                    'upload_id' => $upload->id,
                    'file_size_mb' => $fileSizeMB,
                    'transformer' => 'StreamingCsvTransformer'
                ]);
            } else {
                // Use regular transformer only for very small files (<5MB)
                $this->processCsvTransformation($upload, $csvTransformer, $jobStatusService);

                Log::info('ProcessUploadJob: Used regular transformer for small file', [
                    'upload_id' => $upload->id,
                    'file_size_mb' => $fileSizeMB,
                    'transformer' => 'CsvTransformer'
                ]);
            }

            // Update row count if not set (skip for large files to avoid memory issues)
            if (! $upload->rows_count && $fileSize < 50 * 1024 * 1024) { // Only for files < 50MB
                $content = Storage::disk($upload->disk)->get($upload->path);
                $rowCount = substr_count($content, "\n") + 1;
                $upload->update(['rows_count' => $rowCount]);

                if (!$this->shouldSkipJobStatusTracking()) {
                    $jobStatusService->logJobActivity(
                        jobId: (int) $this->job->getJobId(),
                        level: JobStatusService::LOG_LEVEL_INFO,
                        message: "CSV row count determined: {$rowCount} rows",
                        metadata: ['rows_count' => $rowCount]
                    );
                }
            }

            // Force garbage collection to free up memory
            gc_collect_cycles();

            // Log memory usage after processing
            $currentMemory = memory_get_usage(true);
            Log::info('ProcessUploadJob: Memory usage after processing', [
                'upload_id' => $upload->id,
                'current_memory_mb' => round($currentMemory / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            // Consume credit for successful processing
            $creditService = app(CreditService::class);

            // Only consume credits if this upload hasn't been charged yet
            $alreadyCharged = $upload->credits_consumed > 0;
            $creditConsumed = false;

            if (!$alreadyCharged) {
                $creditConsumed = $creditService->consumeCredits(
                    $upload->user,
                    $upload->credits_required ?? 1,
                    "Procesamiento de archivo CSV: {$upload->original_name}",
                    $upload
                );

                // Update upload record to reflect credits consumed
                if ($creditConsumed) {
                    $upload->update(['credits_consumed' => $upload->credits_required ?? 1]);
                }
            } else {
                // Credits already consumed for this upload
                $creditConsumed = true;
            }

            if (!$creditConsumed) {
                Log::warning('ProcessUploadJob: Failed to consume credit after processing', [
                    'upload_id' => $upload->id,
                    'user_id' => $upload->user_id,
                    'user_credits' => $upload->user->credits,
                ]);
            }

            // Mark as completed in both upload and jobs table
            $this->updateStatus($upload, Upload::STATUS_COMPLETED);

            // Update UploadMetric with completion details
            $uploadMetric->update([
                'processing_completed_at' => now(),
                'credits_consumed' => $creditConsumed ? 1 : 0,
                'status' => UploadMetric::STATUS_COMPLETED,
                'line_count' => $upload->csv_line_count ?? $upload->rows_count ?? 0,
            ]);

            // Update user usage tracking in real-time
            try {
                $usageMeteringService->updateUserUsageCounters(
                    $upload->user,
                    $uploadMetric
                );

                Log::info('ProcessUploadJob: Usage counters updated', [
                    'upload_id' => $upload->id,
                    'user_id' => $upload->user_id,
                    'lines_processed' => $uploadMetric->line_count,
                    'credits_consumed' => $creditConsumed ? 1 : 0,
                ]);

                // Broadcast event to update UI in real-time
                Event::dispatch('livewire:emit', 'usageUpdated', $upload->user_id);

            } catch (\Exception $e) {
                Log::error('Failed to update usage counters', [
                    'upload_id' => $upload->id,
                    'user_id' => $upload->user_id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Send success notification
            try {
                $notificationService->sendSuccessNotification($upload, $uploadMetric);
            } catch (\Exception $e) {
                Log::error('Failed to send success notification', [
                    'upload_id' => $upload->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Skip job status tracking for Redis queues
            if (!$this->shouldSkipJobStatusTracking()) {
                $jobStatusService->updateJobStatus(
                    jobId: (int) $this->job->getJobId(),
                    status: JobStatusService::STATUS_COMPLETED,
                    userId: $upload->user_id,
                    fileName: $upload->original_name
                );
            }

            // Log successful completion (skip for Redis queues)
            if (!$this->shouldSkipJobStatusTracking()) {
                $jobStatusService->logJobActivity(
                    jobId: (int) $this->job->getJobId(),
                level: JobStatusService::LOG_LEVEL_INFO,
                message: 'CSV processing completed successfully',
                metadata: [
                    'upload_id' => $upload->id,
                    'rows_processed' => $upload->rows_count,
                    'credit_consumed' => $creditConsumed,
                    'processing_time_seconds' => time() - $this->job->getJobRecord()->created_at,
                ]
            );
            }

            Log::info('ProcessUploadJob: Processing completed successfully', [
                'upload_id' => $upload->id,
                'rows_processed' => $upload->rows_count,
                'credit_consumed' => $creditConsumed,
                'job_id' => $this->job->getJobId(),
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessUploadJob: Processing failed', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId(),
            ]);

            // Mark as failed in both upload and jobs table
            $upload->update([
                'status' => Upload::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            // Update UploadMetric with failure details
            $uploadMetric->update([
                'processing_completed_at' => now(),
                'status' => UploadMetric::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'credits_consumed' => 0,
            ]);

            // Send failure notification
            try {
                $notificationService->sendFailureNotification($upload, $uploadMetric);
            } catch (\Exception $notificationError) {
                Log::error('Failed to send failure notification', [
                    'upload_id' => $upload->id,
                    'error' => $notificationError->getMessage(),
                ]);
            }

            // Skip job status tracking for Redis queues
            if (!$this->shouldSkipJobStatusTracking()) {
                $jobStatusService->updateJobStatus(
                    jobId: (int) $this->job->getJobId(),
                    status: JobStatusService::STATUS_FAILED,
                    userId: $upload->user_id,
                    fileName: $upload->original_name,
                    errorMessage: $e->getMessage()
                );
            }

            // Log the failure (skip for Redis queues)
            if (!$this->shouldSkipJobStatusTracking()) {
                $jobStatusService->logJobActivity(
                    jobId: (int) $this->job->getJobId(),
                    level: JobStatusService::LOG_LEVEL_ERROR,
                    message: 'CSV processing failed: ' . $e->getMessage(),
                metadata: [
                    'upload_id' => $upload->id,
                    'error_type' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                ]
            );
            }

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

            // Update UploadMetric if it exists
            if ($this->uploadMetricId) {
                $uploadMetric = UploadMetric::find($this->uploadMetricId);
                if ($uploadMetric) {
                    $uploadMetric->update([
                        'processing_completed_at' => now(),
                        'status' => UploadMetric::STATUS_FAILED,
                        'error_message' => $exception->getMessage(),
                        'credits_consumed' => 0,
                    ]);

                    // Send failure notification
                    try {
                        $notificationService = app(NotificationService::class);
                        $notificationService->sendFailureNotification($upload, $uploadMetric);
                    } catch (\Exception $e) {
                        Log::error('Failed to send failure notification in failed() method', [
                            'upload_id' => $upload->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Update job status in jobs table and log the permanent failure
            try {
                $jobStatusService = app(JobStatusService::class);

                // Skip job status tracking for Redis queues
                if (!$this->shouldSkipJobStatusTracking()) {
                    $jobStatusService->updateJobStatus(
                        jobId: (int) $this->job->getJobId(),
                        status: JobStatusService::STATUS_FAILED,
                        userId: $upload->user_id,
                        fileName: $upload->original_name,
                        errorMessage: $exception->getMessage()
                    );

                    $jobStatusService->logJobActivity(
                        jobId: (int) $this->job->getJobId(),
                        level: JobStatusService::LOG_LEVEL_ERROR,
                        message: 'Job failed permanently after ' . $this->attempts() . ' attempts',
                        metadata: [
                            'upload_id' => $upload->id,
                            'attempts' => $this->attempts(),
                            'max_attempts' => $this->tries,
                            'final_error' => $exception->getMessage(),
                            'exception_type' => get_class($exception),
                        ]
                    );
                }
            } catch (\Exception $e) {
                Log::error('Failed to update job status on permanent failure', [
                    'upload_id' => $upload->id,
                    'job_id' => $this->job->getJobId(),
                    'error' => $e->getMessage(),
                ]);
            }

            Log::error('ProcessUploadJob: Job failed permanently', [
                'upload_id' => $upload->id,
                'error' => $exception->getMessage(),
                'attempts' => $this->attempts(),
                'job_id' => $this->job->getJobId(),
            ]);
        }
    }

    /**
     * Update upload status and dispatch real-time events.
     */
    private function updateStatus(Upload $upload, string $status): void
    {
        $oldStatus = $upload->status;
        $upload->update(['status' => $status]);

        // Dispatch event using Livewire's global JavaScript event system
        // This will reach all components listening for upload-status-changed
        try {
            // Write event data to cache for browser polling
            Cache::put("upload_status_event_{$upload->id}", [
                'uploadId' => $upload->id,
                'status' => $status,
                'userId' => $upload->user_id,
                'filename' => $upload->original_name,
                'oldStatus' => $oldStatus,
                'timestamp' => now()->toISOString(),
            ], 60); // Keep for 1 minute

            Log::info('ProcessUploadJob: Status change event cached', [
                'upload_id' => $upload->id,
                'old_status' => $oldStatus,
                'new_status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::debug('Failed to cache upload status change event', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('ProcessUploadJob: Status updated', [
            'upload_id' => $upload->id,
            'new_status' => $status,
        ]);
    }

    /**
     * Check if we should skip job status tracking (for Redis queues).
     */
    private function shouldSkipJobStatusTracking(): bool
    {
        return config('queue.default') === 'redis';
    }

    /**
     * Initialize job metadata in the jobs table.
     */
    private function initializeJobMetadata(Upload $upload, JobStatusService $jobStatusService): void
    {
        // Always try to initialize job metadata for monitoring purposes
        // Even for Redis queues, we want to track job information
        try {
            $jobStatusService->initializeJobMetadata(
                jobId: (int) $this->job->getJobId(),
                userId: $upload->user_id,
                fileName: $upload->original_name,
                status: JobStatusService::STATUS_QUEUED
            );

            logger()->info('Successfully initialized job metadata', [
                'upload_id' => $upload->id,
                'job_id' => $this->job->getJobId(),
                'user_id' => $upload->user_id,
                'queue_driver' => config('queue.default')
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to initialize job metadata', [
                'upload_id' => $upload->id,
                'job_id' => $this->job->getJobId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process CSV transformation using the CsvTransformer service.
     */
    private function processCsvTransformation(Upload $upload, CsvTransformer $csvTransformer, JobStatusService $jobStatusService): void
    {
        try {
                        // Get absolute paths for the transformer
            $inputPath = Storage::disk($upload->disk)->path($upload->path);
            $outputPath = $this->generateOutputPath($upload);

            // Ensure output directory exists
            $outputDirectory = dirname($outputPath);
            if (!Storage::disk($upload->disk)->exists($outputDirectory)) {
                Storage::disk($upload->disk)->makeDirectory($outputDirectory);
            }

            $absoluteOutputPath = Storage::disk($upload->disk)->path($outputPath);

            Log::info('ProcessUploadJob: Starting CSV transformation', [
                'upload_id' => $upload->id,
                'input_path' => $inputPath,
                'output_path' => $absoluteOutputPath,
                'job_id' => $this->job->getJobId(),
            ]);

            // Log transformation start (skip for Redis queues)
            if (!$this->shouldSkipJobStatusTracking()) {
                $jobStatusService->logJobActivity(
                    jobId: (int) $this->job->getJobId(),
                    level: JobStatusService::LOG_LEVEL_INFO,
                    message: 'Starting CSV transformation',
                    metadata: [
                        'input_path' => basename($inputPath),
                        'output_path' => basename($absoluteOutputPath),
                    ]
                );
            }

            // Perform the CSV transformation
            $csvTransformer->transform($inputPath, $absoluteOutputPath);

            // Verify output file was created
            if (!Storage::disk($upload->disk)->exists($outputPath)) {
                throw new \Exception('Transformation completed but output file not found');
            }

            // Save the transformed file path to the upload record
            $upload->update(['transformed_path' => $outputPath]);

            Log::info('ProcessUploadJob: CSV transformation completed', [
                'upload_id' => $upload->id,
                'output_path' => $outputPath,
                'output_size' => Storage::disk($upload->disk)->size($outputPath),
                'job_id' => $this->job->getJobId(),
            ]);

            // Log transformation completion (skip for Redis queues)
            if (!$this->shouldSkipJobStatusTracking()) {
                $jobStatusService->logJobActivity(
                    jobId: (int) $this->job->getJobId(),
                    level: JobStatusService::LOG_LEVEL_INFO,
                    message: 'CSV transformation completed successfully',
                    metadata: [
                        'output_file_size' => Storage::disk($upload->disk)->size($outputPath),
                        'output_path' => basename($outputPath),
                    ]
                );
            }

        } catch (\DomainException $e) {
            // Business logic validation errors
            Log::warning('ProcessUploadJob: Validation failed during transformation', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
                'type' => 'validation_error',
                'job_id' => $this->job->getJobId(),
            ]);

            // Log validation error
            $jobStatusService->logJobActivity(
                jobId: (int) $this->job->getJobId(),
                level: JobStatusService::LOG_LEVEL_WARNING,
                message: 'CSV validation failed: ' . $e->getMessage(),
                metadata: [
                    'error_type' => 'validation_error',
                    'validation_message' => $e->getMessage(),
                ]
            );

            throw new \Exception('CSV validation failed: ' . $e->getMessage());

        } catch (\Exception $e) {
            Log::error('ProcessUploadJob: Transformation failed', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId(),
            ]);

            // Log transformation error
            $jobStatusService->logJobActivity(
                jobId: (int) $this->job->getJobId(),
                level: JobStatusService::LOG_LEVEL_ERROR,
                message: 'CSV transformation failed: ' . $e->getMessage(),
                metadata: [
                    'error_type' => get_class($e),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Process CSV transformation using the StreamingCsvTransformer service for large files.
     */
    private function processCsvTransformationStreaming(Upload $upload, StreamingCsvTransformer $streamingTransformer, JobStatusService $jobStatusService): void
    {
        try {
            // Get absolute paths for the transformer
            $inputPath = Storage::disk($upload->disk)->path($upload->path);
            $outputPath = $this->generateOutputPath($upload);

            // Ensure output directory exists
            $outputDirectory = dirname($outputPath);
            if (!Storage::disk($upload->disk)->exists($outputDirectory)) {
                Storage::disk($upload->disk)->makeDirectory($outputDirectory);
            }

            $absoluteOutputPath = Storage::disk($upload->disk)->path($outputPath);

            Log::info('ProcessUploadJob: Starting streaming CSV transformation', [
                'upload_id' => $upload->id,
                'input_path' => $inputPath,
                'output_path' => $absoluteOutputPath,
                'job_id' => $this->job->getJobId(),
            ]);

            // Perform the streaming CSV transformation
            $streamingTransformer->transform($inputPath, $absoluteOutputPath);

            // Verify output file was created
            if (!Storage::disk($upload->disk)->exists($outputPath)) {
                throw new \Exception('Streaming transformation completed but output file not found');
            }

            // Save the transformed file path to the upload record
            $upload->update(['transformed_path' => $outputPath]);

            Log::info('ProcessUploadJob: Streaming CSV transformation completed', [
                'upload_id' => $upload->id,
                'output_path' => $outputPath,
                'output_size' => Storage::disk($upload->disk)->size($outputPath),
                'job_id' => $this->job->getJobId(),
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessUploadJob: Streaming CSV transformation failed', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
                'job_id' => $this->job->getJobId(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate output file path for the transformed CSV.
     * Format: uploads/{user_id}/output/{original_filename}_{date}_transformado.csv
     */
    private function generateOutputPath(Upload $upload): string
    {
        // Extract original filename without extension
        $pathInfo = pathinfo($upload->original_name);
        $originalFilename = $pathInfo['filename'] ?? 'archivo';

        // Get current date for filename
        $dateStr = now()->format('Y-m-d_H-i-s');

        // Create output filename with date
        $outputFilename = $originalFilename . '_' . $dateStr . '_transformado.csv';

        // Generate output path: uploads/{user_id}/output/{filename}
        $outputPath = 'uploads/' . $upload->user_id . '/output/' . $outputFilename;

        return $outputPath;
    }
}
