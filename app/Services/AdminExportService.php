<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Upload;
use App\Models\UploadMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class AdminExportService
{
    private const MAX_EXPORT_LIMIT = 1000;
    private const DEFAULT_LIMIT = 1000;

    /**
     * Export uploads to CSV format
     */
    public function exportUploads(array $filters = []): string
    {
        $this->validateFilters($filters, ['status', 'user_id', 'date_from', 'date_to', 'limit']);

        $query = Upload::with(['user'])
            ->orderBy('created_at', 'desc');

        $this->applyUploadFilters($query, $filters);

        $limit = min($filters['limit'] ?? self::DEFAULT_LIMIT, self::MAX_EXPORT_LIMIT);
        $uploads = $query->limit($limit)->get();

        $headers = [
            'ID',
            'User Name',
            'Email',
            'Filename',
            'Size',
            'Status',
            'Created At',
            'Processed At',
            'Failure Reason',
        ];

        $rows = $uploads->map(function ($upload) {
            return [
                $upload->id,
                $upload->user->name,
                $upload->user->email,
                $upload->original_name,
                $upload->formatted_size,
                ucfirst($upload->status),
                $upload->created_at->format('Y-m-d H:i:s'),
                $upload->processed_at?->format('Y-m-d H:i:s') ?? '',
                $upload->failure_reason ?? '',
            ];
        });

        return $this->generateCsv($headers, $rows->toArray());
    }

    /**
     * Export users to CSV format with statistics
     */
    public function exportUsers(array $filters = []): string
    {
        $this->validateFilters($filters, ['has_uploads', 'date_from', 'date_to', 'limit']);

        $query = User::with(['uploads'])
            ->withCount('uploads')
            ->orderBy('created_at', 'desc');

        $this->applyUserFilters($query, $filters);

        $limit = min($filters['limit'] ?? self::DEFAULT_LIMIT, self::MAX_EXPORT_LIMIT);
        $users = $query->limit($limit)->get();

        $headers = [
            'ID',
            'Name',
            'Email',
            'Created At',
            'Uploads Count',
            'Total Size',
            'Success Rate',
            'Credits',
        ];

        $rows = $users->map(function ($user) {
            $totalSize = $user->uploads->sum('size_bytes');
            $completedUploads = $user->uploads->where('status', Upload::STATUS_COMPLETED)->count();
            $successRate = $user->uploads_count > 0
                ? round(($completedUploads / $user->uploads_count) * 100, 1)
                : 0;

            return [
                $user->id,
                $user->name,
                $user->email,
                $user->created_at->format('Y-m-d H:i:s'),
                $user->uploads_count,
                $this->formatBytes($totalSize),
                $successRate . '%',
                $user->credits ?? 0,
            ];
        });

        return $this->generateCsv($headers, $rows->toArray());
    }

    /**
     * Export system metrics to CSV format
     */
    public function exportSystemMetrics(): string
    {
        $totalUsers = User::count();
        $totalUploads = Upload::count();
        $completedUploads = Upload::where('status', Upload::STATUS_COMPLETED)->count();
        $failedUploads = Upload::where('status', Upload::STATUS_FAILED)->count();

        $successRate = $totalUploads > 0 ? round(($completedUploads / $totalUploads) * 100, 1) : 0;
        $errorRate = $totalUploads > 0 ? round(($failedUploads / $totalUploads) * 100, 1) : 0;

        $totalStorage = Upload::sum('size_bytes') ?? 0;
        $averageFileSize = Upload::avg('size_bytes') ?? 0;

        $headers = ['Metric', 'Value', 'Date Generated'];

        $rows = [
            ['Total Users', $totalUsers, now()->format('Y-m-d H:i:s')],
            ['Total Uploads', $totalUploads, now()->format('Y-m-d H:i:s')],
            ['Completed Uploads', $completedUploads, now()->format('Y-m-d H:i:s')],
            ['Failed Uploads', $failedUploads, now()->format('Y-m-d H:i:s')],
            ['Success Rate (%)', $successRate, now()->format('Y-m-d H:i:s')],
            ['Error Rate (%)', $errorRate, now()->format('Y-m-d H:i:s')],
            ['Total Storage Used', $this->formatBytes($totalStorage), now()->format('Y-m-d H:i:s')],
            ['Average File Size', $this->formatBytes($averageFileSize), now()->format('Y-m-d H:i:s')],
        ];

        return $this->generateCsv($headers, $rows);
    }

    /**
     * Export upload metrics to CSV format
     */
    public function exportUploadMetrics(array $filters = []): string
    {
        $query = UploadMetric::with(['upload.user', 'user'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $limit = min($filters['limit'] ?? self::DEFAULT_LIMIT, self::MAX_EXPORT_LIMIT);
        $metrics = $query->limit($limit)->get();

        $headers = [
            'Upload ID',
            'User',
            'File Name',
            'File Size',
            'Line Count',
            'Processing Time (seconds)',
            'Credits Consumed',
            'Status',
            'Created At',
        ];

        $rows = $metrics->map(function ($metric) {
            return [
                $metric->upload_id,
                $metric->user->name ?? 'Unknown',
                $metric->file_name,
                $this->formatBytes($metric->file_size_bytes),
                $metric->line_count,
                $metric->processing_duration_seconds ?? 0,
                $metric->credits_consumed,
                ucfirst($metric->status),
                $metric->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return $this->generateCsv($headers, $rows->toArray());
    }

    /**
     * Save uploads export to storage and return filename
     */
    public function saveUploadsExport(array $filters = []): string
    {
        $csvContent = $this->exportUploads($filters);
        $filename = $this->generateExportFilename('uploads');

        Storage::disk('local')->put($filename, $csvContent);

        return $filename;
    }

    /**
     * Save users export to storage and return filename
     */
    public function saveUsersExport(array $filters = []): string
    {
        $csvContent = $this->exportUsers($filters);
        $filename = $this->generateExportFilename('users');

        Storage::disk('local')->put($filename, $csvContent);

        return $filename;
    }

    /**
     * Generate export filename with timestamp
     */
    public function generateExportFilename(string $type): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        return "exports/{$type}_{$timestamp}.csv";
    }

    /**
     * Apply filters to upload query
     */
    private function applyUploadFilters(Builder $query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    /**
     * Apply filters to user query
     */
    private function applyUserFilters(Builder $query, array $filters): void
    {
        if (isset($filters['has_uploads']) && $filters['has_uploads']) {
            $query->has('uploads');
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    /**
     * Generate CSV content from headers and rows
     */
    private function generateCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        // Write headers
        fputcsv($output, $headers);

        // Write rows
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    /**
     * Validate export filters
     */
    private function validateFilters(array $filters, array $allowedFilters): void
    {
        $invalidFilters = array_diff(array_keys($filters), $allowedFilters);

        if (!empty($invalidFilters)) {
            throw new InvalidArgumentException(
                'Invalid filters: ' . implode(', ', $invalidFilters)
            );
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(float $bytes): string
    {
        if ($bytes === 0 || $bytes === 0.0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $power = max($power, 0);

        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}

