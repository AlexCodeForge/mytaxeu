<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Upload;
use App\Services\CreditService;
use App\Services\CsvTransformer;
use App\Services\JobStatusService;
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
    public function handle(CsvTransformer $csvTransformer, JobStatusService $jobStatusService): void
    {
        $upload = Upload::find($this->uploadId);

        if (! $upload) {
            Log::error('ProcessUploadJob: Upload not found', ['upload_id' => $this->uploadId]);
            return;
        }

        // Initialize job metadata in the jobs table
        $this->initializeJobMetadata($upload, $jobStatusService);

        try {
            // Set status to processing in both upload and jobs table
            $this->updateStatus($upload, Upload::STATUS_PROCESSING);
            $jobStatusService->updateJobStatus(
                jobId: $this->job->getJobId(),
                status: JobStatusService::STATUS_PROCESSING,
                userId: $upload->user_id,
                fileName: $upload->original_name
            );

            // Log job activity
            $jobStatusService->logJobActivity(
                jobId: $this->job->getJobId(),
                level: JobStatusService::LOG_LEVEL_INFO,
                message: 'Started processing CSV file',
                metadata: [
                    'upload_id' => $upload->id,
                    'user_id' => $upload->user_id,
                    'filename' => $upload->original_name,
                    'file_size' => $upload->size_bytes,
                ]
            );

            Log::info('ProcessUploadJob: Started processing', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'filename' => $upload->original_name,
                'job_id' => $this->job->getJobId(),
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

            // Log validation step
            $jobStatusService->logJobActivity(
                jobId: $this->job->getJobId(),
                level: JobStatusService::LOG_LEVEL_INFO,
                message: 'File validation completed successfully',
                metadata: [
                    'file_size' => $upload->size_bytes,
                    'file_exists' => true,
                ]
            );

            // Process CSV with transformation
            $this->processCsvTransformation($upload, $csvTransformer, $jobStatusService);

            // Update row count if not set
            if (! $upload->rows_count) {
                $rowCount = substr_count($content, "\n") + 1;
                $upload->update(['rows_count' => $rowCount]);

                $jobStatusService->logJobActivity(
                    jobId: $this->job->getJobId(),
                    level: JobStatusService::LOG_LEVEL_INFO,
                    message: "CSV row count determined: {$rowCount} rows",
                    metadata: ['rows_count' => $rowCount]
                );
            }

            // Simulate processing time (remove in production)
            sleep(2);

            // Consume credit for successful processing
            $creditService = app(CreditService::class);
            $creditConsumed = $creditService->consumeCredits(
                $upload->user,
                1,
                "Procesamiento de archivo CSV: {$upload->original_name}",
                $upload
            );

            if (!$creditConsumed) {
                Log::warning('ProcessUploadJob: Failed to consume credit after processing', [
                    'upload_id' => $upload->id,
                    'user_id' => $upload->user_id,
                    'user_credits' => $upload->user->credits,
                ]);
            }

            // Mark as completed in both upload and jobs table
            $this->updateStatus($upload, Upload::STATUS_COMPLETED);
            $jobStatusService->updateJobStatus(
                jobId: $this->job->getJobId(),
                status: JobStatusService::STATUS_COMPLETED,
                userId: $upload->user_id,
                fileName: $upload->original_name
            );

            // Log successful completion
            $jobStatusService->logJobActivity(
                jobId: $this->job->getJobId(),
                level: JobStatusService::LOG_LEVEL_INFO,
                message: 'CSV processing completed successfully',
                metadata: [
                    'upload_id' => $upload->id,
                    'rows_processed' => $upload->rows_count,
                    'credit_consumed' => $creditConsumed,
                    'processing_time_seconds' => time() - $this->job->getJobRecord()->created_at,
                ]
            );

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

            $jobStatusService->updateJobStatus(
                jobId: $this->job->getJobId(),
                status: JobStatusService::STATUS_FAILED,
                userId: $upload->user_id,
                fileName: $upload->original_name,
                errorMessage: $e->getMessage()
            );

            // Log the failure
            $jobStatusService->logJobActivity(
                jobId: $this->job->getJobId(),
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

            // Update job status in jobs table and log the permanent failure
            try {
                $jobStatusService = app(JobStatusService::class);

                $jobStatusService->updateJobStatus(
                    jobId: $this->job->getJobId(),
                    status: JobStatusService::STATUS_FAILED,
                    userId: $upload->user_id,
                    fileName: $upload->original_name,
                    errorMessage: $exception->getMessage()
                );

                $jobStatusService->logJobActivity(
                    jobId: $this->job->getJobId(),
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
     * Initialize job metadata in the jobs table.
     */
    private function initializeJobMetadata(Upload $upload, JobStatusService $jobStatusService): void
    {
        try {
            $jobStatusService->initializeJobMetadata(
                jobId: $this->job->getJobId(),
                userId: $upload->user_id,
                fileName: $upload->original_name,
                status: JobStatusService::STATUS_QUEUED
            );
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
            $absoluteOutputPath = Storage::disk($upload->disk)->path($outputPath);

            Log::info('ProcessUploadJob: Starting CSV transformation', [
                'upload_id' => $upload->id,
                'input_path' => $inputPath,
                'output_path' => $absoluteOutputPath,
                'job_id' => $this->job->getJobId(),
            ]);

            // Log transformation start
            $jobStatusService->logJobActivity(
                jobId: $this->job->getJobId(),
                level: JobStatusService::LOG_LEVEL_INFO,
                message: 'Starting CSV transformation',
                metadata: [
                    'input_path' => basename($inputPath),
                    'output_path' => basename($absoluteOutputPath),
                ]
            );

            // Perform the CSV transformation
            $csvTransformer->transform($inputPath, $absoluteOutputPath);

            // Verify output file was created
            if (!Storage::disk($upload->disk)->exists($outputPath)) {
                throw new \Exception('Transformation completed but output file not found');
            }

            Log::info('ProcessUploadJob: CSV transformation completed', [
                'upload_id' => $upload->id,
                'output_path' => $outputPath,
                'output_size' => Storage::disk($upload->disk)->size($outputPath),
                'job_id' => $this->job->getJobId(),
            ]);

            // Log transformation completion
            $jobStatusService->logJobActivity(
                jobId: $this->job->getJobId(),
                level: JobStatusService::LOG_LEVEL_INFO,
                message: 'CSV transformation completed successfully',
                metadata: [
                    'output_file_size' => Storage::disk($upload->disk)->size($outputPath),
                    'output_path' => basename($outputPath),
                ]
            );

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
                jobId: $this->job->getJobId(),
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
                jobId: $this->job->getJobId(),
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
     * Generate output file path for the transformed CSV.
     */
    private function generateOutputPath(Upload $upload): string
    {
        $pathInfo = pathinfo($upload->path);
        $directory = $pathInfo['dirname'] ?? '';
        $filename = $pathInfo['filename'] ?? 'transformed';

        $outputFilename = $filename . '_transformed.csv';

        return $directory ? $directory . '/' . $outputFilename : $outputFilename;
    }
}
