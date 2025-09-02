<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private AdminExportService $exportService
    ) {
        $this->middleware(['auth', 'verified', 'ensure.admin']);
    }

    /**
     * Generate uploads export and return signed download URL
     */
    public function uploadsExport(Request $request)
    {
        $filters = $request->only(['status', 'user_id', 'date_from', 'date_to', 'limit']);

        // Clean empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        try {
            $filename = $this->exportService->saveUploadsExport($filters);

            // Generate signed URL valid for 1 hour
            $signedUrl = URL::temporarySignedRoute(
                'admin.exports.download',
                now()->addHour(),
                ['filename' => basename($filename)]
            );

            return response()->json([
                'success' => true,
                'download_url' => $signedUrl,
                'filename' => basename($filename),
                'expires_at' => now()->addHour()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate users export and return signed download URL
     */
    public function usersExport(Request $request)
    {
        $filters = $request->only(['has_uploads', 'date_from', 'date_to', 'limit']);

        // Clean empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        try {
            $filename = $this->exportService->saveUsersExport($filters);

            // Generate signed URL valid for 1 hour
            $signedUrl = URL::temporarySignedRoute(
                'admin.exports.download',
                now()->addHour(),
                ['filename' => basename($filename)]
            );

            return response()->json([
                'success' => true,
                'download_url' => $signedUrl,
                'filename' => basename($filename),
                'expires_at' => now()->addHour()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate system metrics export and return signed download URL
     */
    public function systemMetricsExport(Request $request)
    {
        try {
            $csvContent = $this->exportService->exportSystemMetrics();
            $filename = $this->exportService->generateExportFilename('system_metrics');

            Storage::disk('local')->put($filename, $csvContent);

            // Generate signed URL valid for 1 hour
            $signedUrl = URL::temporarySignedRoute(
                'admin.exports.download',
                now()->addHour(),
                ['filename' => basename($filename)]
            );

            return response()->json([
                'success' => true,
                'download_url' => $signedUrl,
                'filename' => basename($filename),
                'expires_at' => now()->addHour()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate upload metrics export and return signed download URL
     */
    public function uploadMetricsExport(Request $request)
    {
        $filters = $request->only(['status', 'limit']);

        // Clean empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        try {
            $csvContent = $this->exportService->exportUploadMetrics($filters);
            $filename = $this->exportService->generateExportFilename('upload_metrics');

            Storage::disk('local')->put($filename, $csvContent);

            // Generate signed URL valid for 1 hour
            $signedUrl = URL::temporarySignedRoute(
                'admin.exports.download',
                now()->addHour(),
                ['filename' => basename($filename)]
            );

            return response()->json([
                'success' => true,
                'download_url' => $signedUrl,
                'filename' => basename($filename),
                'expires_at' => now()->addHour()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download export file using signed URL
     */
    public function downloadExport(Request $request, string $filename)
    {
        // Validate signed URL
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired download link');
        }

        $filePath = 'exports/' . $filename;

        // Check if file exists
        if (!Storage::disk('local')->exists($filePath)) {
            abort(404, 'Export file not found');
        }

        // Security check: ensure filename doesn't contain path traversal
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            abort(403, 'Invalid filename');
        }

        // Log the download for audit purposes
        logger()->info('Admin export downloaded', [
            'filename' => $filename,
            'user_id' => Auth::id(),
            'user_email' => Auth::user()->email,
            'ip' => $request->ip(),
        ]);

        return Storage::disk('local')->download($filePath, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get instant CSV download (for smaller exports)
     */
    public function instantUploadsExport(Request $request): StreamedResponse
    {
        $filters = $request->only(['status', 'user_id', 'date_from', 'date_to']);
        $filters['limit'] = 500; // Limit for instant downloads

        // Clean empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $filename = 'uploads_export_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            echo $this->exportService->exportUploads($filters);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Get instant CSV download for users (for smaller exports)
     */
    public function instantUsersExport(Request $request): StreamedResponse
    {
        $filters = $request->only(['has_uploads', 'date_from', 'date_to']);
        $filters['limit'] = 500; // Limit for instant downloads

        // Clean empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $filename = 'users_export_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            echo $this->exportService->exportUsers($filters);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Cleanup old export files
     */
    public function cleanupOldExports(): Response
    {
        try {
            $files = Storage::disk('local')->files('exports');
            $deletedCount = 0;

            foreach ($files as $file) {
                $lastModified = Storage::disk('local')->lastModified($file);

                // Delete files older than 24 hours
                if ($lastModified < now()->subDay()->timestamp) {
                    Storage::disk('local')->delete($file);
                    $deletedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Cleaned up {$deletedCount} old export files",
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup exports: ' . $e->getMessage(),
            ], 500);
        }
    }
}

