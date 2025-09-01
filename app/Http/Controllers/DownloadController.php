<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function __construct()
    {
        // Apply rate limiting: 60 downloads per minute per user
        $this->middleware('throttle:downloads')->only('downloadUpload');
    }

    /**
     * Download a processed upload file.
     */
    public function downloadUpload(Request $request, Upload $upload): StreamedResponse
    {
        $user = auth()->user();
        $userAgent = $request->header('User-Agent');
        $ipAddress = $request->ip();

        // Log download attempt
        Log::info('Download attempt', [
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'file_name' => $upload->original_name,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Ensure user owns the upload
        if ($upload->user_id !== auth()->id()) {
            Log::warning('Unauthorized download attempt', [
                'user_id' => $user->id,
                'upload_id' => $upload->id,
                'owner_id' => $upload->user_id,
                'ip_address' => $ipAddress,
            ]);
            abort(403, 'Unauthorized access to file');
        }

        // Ensure upload is completed and has transformed file
        if (!$upload->hasTransformedFile()) {
            Log::warning('Download attempt for file without transformed version', [
                'user_id' => $user->id,
                'upload_id' => $upload->id,
                'upload_status' => $upload->status,
                'has_transformed_path' => !empty($upload->transformed_path),
            ]);
            abort(404, 'File not found or not yet processed');
        }

        // Get file path and ensure it exists
        $filePath = $upload->transformed_path;
        if (!Storage::disk($upload->disk)->exists($filePath)) {
            Log::error('Download attempt for missing file', [
                'user_id' => $user->id,
                'upload_id' => $upload->id,
                'file_path' => $filePath,
                'disk' => $upload->disk,
            ]);
            abort(404, 'File not found in storage');
        }

        // Generate download filename
        $fileName = $this->generateDownloadFilename($upload);

        // Log successful download
        Log::info('File download successful', [
            'user_id' => $user->id,
            'upload_id' => $upload->id,
            'file_name' => $fileName,
            'file_size' => Storage::disk($upload->disk)->size($filePath),
            'ip_address' => $ipAddress,
        ]);

        // Stream the file download
        return Storage::disk($upload->disk)->download($filePath, $fileName);
    }

    /**
     * Generate a user-friendly download filename.
     */
    private function generateDownloadFilename(Upload $upload): string
    {
        $originalName = pathinfo($upload->original_name, PATHINFO_FILENAME);
        $date = $upload->processed_at?->format('Y-m-d') ?? date('Y-m-d');

        return $originalName . '_procesado_' . $date . '.csv';
    }
}
