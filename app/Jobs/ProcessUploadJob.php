<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Upload;
use App\Services\CreditService;
use App\Services\CsvTransformer;
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
    public function handle(CsvTransformer $csvTransformer): void
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

            // Process CSV with transformation
            $this->processCsvTransformation($upload, $csvTransformer);

            // Update row count if not set
            if (! $upload->rows_count) {
                $rowCount = substr_count($content, "\n") + 1;
                $upload->update(['rows_count' => $rowCount]);
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

            // Mark as completed
            $this->updateStatus($upload, Upload::STATUS_COMPLETED);

            Log::info('ProcessUploadJob: Processing completed successfully', [
                'upload_id' => $upload->id,
                'rows_processed' => $upload->rows_count,
                'credit_consumed' => $creditConsumed,
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
     * Process CSV transformation using the CsvTransformer service.
     */
    private function processCsvTransformation(Upload $upload, CsvTransformer $csvTransformer): void
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
            ]);

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
            ]);

        } catch (\DomainException $e) {
            // Business logic validation errors
            Log::warning('ProcessUploadJob: Validation failed during transformation', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
                'type' => 'validation_error',
            ]);
            
            throw new \Exception('CSV validation failed: ' . $e->getMessage());

        } catch (\Exception $e) {
            Log::error('ProcessUploadJob: Transformation failed', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
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
