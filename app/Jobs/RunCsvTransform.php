<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Upload;
use App\Services\CsvTransformer;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RunCsvTransform implements ShouldQueue
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
    public int $timeout = 600; // 10 minutes for complex transformations

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
    public function handle(CsvTransformer $transformer): void
    {
        $upload = Upload::find($this->uploadId);

        if (!$upload) {
            Log::error('RunCsvTransform: Upload not found', ['upload_id' => $this->uploadId]);
            return;
        }

        try {
            // Update status to processing transformation
            $upload->update([
                'status' => Upload::STATUS_PROCESSING,
                'processed_at' => now(),
            ]);

            Log::info('RunCsvTransform: Started CSV transformation', [
                'upload_id' => $upload->id,
                'user_id' => $upload->user_id,
                'filename' => $upload->original_name,
            ]);

            // Verify input file exists
            $inputPath = $this->getStoragePath($upload);
            if (!Storage::disk($upload->disk)->exists($upload->path)) {
                throw new RuntimeException('Input file not found in storage: ' . $upload->path);
            }

            // Generate output file path
            $outputPath = $this->generateOutputPath($upload);

            // Ensure output directory exists
            $outputDirectory = dirname($outputPath);
            if (!Storage::disk($upload->disk)->exists($outputDirectory)) {
                Storage::disk($upload->disk)->makeDirectory($outputDirectory);
            }

            // Get absolute paths for the transformer
            $absoluteInputPath = Storage::disk($upload->disk)->path($upload->path);
            $absoluteOutputPath = Storage::disk($upload->disk)->path($outputPath);

            Log::info('RunCsvTransform: Starting transformation', [
                'upload_id' => $upload->id,
                'input_path' => $absoluteInputPath,
                'output_path' => $absoluteOutputPath,
            ]);

            // Perform the CSV transformation
            $transformer->transform($absoluteInputPath, $absoluteOutputPath);

            // Verify output file was created
            if (!Storage::disk($upload->disk)->exists($outputPath)) {
                throw new RuntimeException('Transformation completed but output file not found');
            }

            // Update upload record with completion status and save transformed file path
            $upload->update([
                'status' => Upload::STATUS_COMPLETED,
                'transformed_path' => $outputPath,
                'processed_at' => now(),
            ]);

            Log::info('RunCsvTransform: Transformation completed successfully', [
                'upload_id' => $upload->id,
                'input_size' => $upload->size_bytes,
                'output_path' => $outputPath,
                'output_size' => Storage::disk($upload->disk)->size($outputPath),
                'processing_time_seconds' => now()->diffInSeconds($upload->updated_at),
            ]);

        } catch (DomainException $e) {
            // Business logic validation errors - don't retry
            $this->handleValidationFailure($upload, $e);

            Log::warning('RunCsvTransform: Validation failed', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
                'type' => 'validation_error',
            ]);

            // Don't retry validation errors
            $this->delete();

        } catch (RuntimeException $e) {
            // Runtime errors (file I/O, etc.) - may be transient
            $this->handleRuntimeFailure($upload, $e);

            Log::error('RunCsvTransform: Runtime error occurred', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
                'type' => 'runtime_error',
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to allow job retry
            throw $e;

        } catch (\Exception $e) {
            // Unexpected errors
            $this->handleUnexpectedFailure($upload, $e);

            Log::error('RunCsvTransform: Unexpected error occurred', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
                'type' => 'unexpected_error',
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to allow job retry
            throw $e;
        }
    }

    /**
     * Handle job failure after all retry attempts.
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

            Log::error('RunCsvTransform: Job failed permanently', [
                'upload_id' => $upload->id,
                'error' => $exception->getMessage(),
                'attempts' => $this->attempts(),
                'exception_type' => get_class($exception),
            ]);
        }
    }

    /**
     * Get the storage path for the upload file.
     */
    private function getStoragePath(Upload $upload): string
    {
        return $upload->path;
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

    /**
     * Handle validation failures (don't retry).
     */
    private function handleValidationFailure(Upload $upload, DomainException $e): void
    {
        $upload->update([
            'status' => Upload::STATUS_FAILED,
            'failure_reason' => 'Validation error: ' . $e->getMessage(),
            'processed_at' => now(),
        ]);
    }

    /**
     * Handle runtime failures (may retry).
     */
    private function handleRuntimeFailure(Upload $upload, RuntimeException $e): void
    {
        $upload->update([
            'failure_reason' => 'Runtime error: ' . $e->getMessage(),
        ]);
    }

    /**
     * Handle unexpected failures (may retry).
     */
    private function handleUnexpectedFailure(Upload $upload, \Exception $e): void
    {
        $upload->update([
            'failure_reason' => 'Unexpected error: ' . $e->getMessage(),
        ]);
    }
}
