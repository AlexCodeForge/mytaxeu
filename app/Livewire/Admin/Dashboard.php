<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\Upload;
use App\Models\UploadMetric;
use App\Services\UsageMeteringService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void
    {
        // Ensure user is admin
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Acceso denegado');
        }
    }

    public function getMetricsProperty(): array
    {
        $totalUsers = User::count();
        $totalUploads = Upload::count();
        $uploadsToday = Upload::whereDate('created_at', today())->count();
        $uploadsThisWeek = Upload::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $uploadsThisMonth = Upload::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();

        // New users today
        $newUsersToday = User::whereDate('created_at', today())->count();

        // Calculate success rate
        $completedUploads = Upload::where('status', Upload::STATUS_COMPLETED)->count();
        $successRate = $totalUploads > 0 ? ($completedUploads / $totalUploads) * 100 : 0;

        // Calculate error rate
        $failedUploads = Upload::where('status', Upload::STATUS_FAILED)->count();
        $errorRate = $totalUploads > 0 ? ($failedUploads / $totalUploads) * 100 : 0;

        // Users with active subscriptions
        $activeUsers = User::whereHas('subscriptions', function ($query) {
            $query->where(function ($validQuery) {
                // Active subscriptions
                $validQuery->where('stripe_status', 'active')
                    // OR on trial (trial_ends_at is in the future)
                    ->orWhere(function ($trialQuery) {
                        $trialQuery->whereNotNull('trial_ends_at')
                                   ->where('trial_ends_at', '>', now());
                    })
                    // OR on grace period (ends_at is in the future but not canceled)
                    ->orWhere(function ($graceQuery) {
                        $graceQuery->where('stripe_status', '!=', 'canceled')
                                   ->whereNotNull('ends_at')
                                   ->where('ends_at', '>', now());
                    });
            });
        })->count();

        // Active subscription percentage
        $activePercentage = $totalUsers > 0 ? ($activeUsers / $totalUsers) * 100 : 0;

        return [
            'total_users' => $totalUsers,
            'total_uploads' => $totalUploads,
            'uploads_today' => $uploadsToday,
            'uploads_this_week' => $uploadsThisWeek,
            'uploads_this_month' => $uploadsThisMonth,
            'new_users_today' => $newUsersToday,
            'success_rate' => round($successRate, 1),
            'active_users' => $activeUsers,
            'active_percentage' => round($activePercentage, 1),
            'error_rate' => round($errorRate, 1),
        ];
    }

    public function getStatusBreakdownProperty(): array
    {
        return [
            'received' => Upload::where('status', Upload::STATUS_RECEIVED)->count(),
            'queued' => Upload::where('status', Upload::STATUS_QUEUED)->count(),
            'processing' => Upload::where('status', Upload::STATUS_PROCESSING)->count(),
            'completed' => Upload::where('status', Upload::STATUS_COMPLETED)->count(),
            'failed' => Upload::where('status', Upload::STATUS_FAILED)->count(),
        ];
    }

    public function getRecentActivityProperty(): \Illuminate\Support\Collection
    {
        return Upload::with(['user'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($upload) {
                return [
                    'id' => $upload->id,
                    'type' => 'upload',
                    'description' => "Carga '{$upload->original_name}' por {$upload->user->name}",
                    'status' => $upload->status,
                    'timestamp' => $upload->created_at,
                    'time' => $upload->created_at->diffForHumans(),
                    'user' => $upload->user->name,
                    'user_email' => $upload->user->email,
                ];
            });
    }

    public function getRecentUsersProperty(): \Illuminate\Support\Collection
    {
        return User::latest()
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                    'uploads_count' => $user->uploads()->count(),
                ];
            });
    }

    public function getSystemHealthProperty(): array
    {
        $queuedJobs = Upload::where('status', Upload::STATUS_QUEUED)->count();
        $processingJobs = Upload::where('status', Upload::STATUS_PROCESSING)->count();
        $failedJobsToday = Upload::where('status', Upload::STATUS_FAILED)
            ->whereDate('created_at', today())
            ->count();

        // Check database connectivity
        $databaseStatus = $this->checkDatabaseHealth();

        // Check storage status
        $storageStatus = $this->checkStorageHealth();

        // Simple health scoring
        $healthScore = 100;
        if ($queuedJobs > 50) $healthScore -= 20;
        if ($processingJobs > 20) $healthScore -= 15;
        if ($failedJobsToday > 10) $healthScore -= 25;
        if ($databaseStatus !== 'healthy') $healthScore -= 30;
        if ($storageStatus === 'warning') $healthScore -= 10;
        if ($storageStatus === 'critical') $healthScore -= 20;

        $healthStatus = match (true) {
            $healthScore >= 90 => 'excellent',
            $healthScore >= 70 => 'good',
            $healthScore >= 50 => 'warning',
            default => 'critical'
        };

        return [
            'score' => max(0, $healthScore),
            'status' => $healthStatus,
            'queued_jobs' => $queuedJobs,
            'processing_jobs' => $processingJobs,
            'failed_jobs_today' => $failedJobsToday,
            'queue_status' => $queuedJobs < 50 ? 'healthy' : 'warning',
            'storage_status' => $storageStatus,
            'database_status' => $databaseStatus,
        ];
    }

    public function getStorageMetricsProperty(): array
    {
        $totalStorageUsed = Upload::sum('size_bytes') ?? 0;
        $averageFileSize = Upload::avg('size_bytes') ?? 0;
        $uploadCount = Upload::count();
        $filesToday = Upload::whereDate('created_at', today())->count();

        return [
            'total_storage_used' => $totalStorageUsed,
            'total_storage_formatted' => $this->formatBytes((float) $totalStorageUsed),
            'average_file_size' => $averageFileSize,
            'average_file_size_formatted' => $this->formatBytes((float) $averageFileSize),
            'upload_count' => $uploadCount,
            'files_today' => $filesToday,
        ];
    }

    public function getPerformanceMetricsProperty(): array
    {
        $averageProcessingTime = UploadMetric::where('status', UploadMetric::STATUS_COMPLETED)
            ->avg('processing_duration_seconds') ?? 0;

        $totalProcessingTime = UploadMetric::where('status', UploadMetric::STATUS_COMPLETED)
            ->sum('processing_duration_seconds') ?? 0;

        $processedCount = UploadMetric::where('status', UploadMetric::STATUS_COMPLETED)->count();

        return [
            'average_processing_time' => round((float) $averageProcessingTime, 2),
            'total_processing_time' => (float) $totalProcessingTime,
            'processed_count' => $processedCount,
            'average_processing_time_formatted' => $this->formatDuration((float) $averageProcessingTime),
        ];
    }

    public function getUsageAnalyticsProperty(): array
    {
        $usageMeteringService = app(UsageMeteringService::class);
        return $usageMeteringService->getSystemUsageStatistics();
    }

    public function getTrendDataProperty(): array
    {
        // Get upload trends for the last 7 days
        $trends = [];
        $spanishMonths = [
            'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Abr',
            'May' => 'May', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
            'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'
        ];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = Upload::whereDate('created_at', $date)->count();
            $monthAbbr = $date->format('M');
            $spanishMonth = $spanishMonths[$monthAbbr] ?? $monthAbbr;

            $trends[] = [
                'date' => $spanishMonth . ' ' . $date->format('j'),
                'uploads' => $count,
            ];
        }


        return $trends;
    }

    private function formatBytes(float $bytes): string
    {
        if ($bytes === 0 || $bytes === 0.0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $power = max($power, 0); // Ensure power is not negative

        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1) . 'm';
        } else {
            return round($seconds / 3600, 1) . 'h';
        }
    }

    private function checkDatabaseHealth(): string
    {
        try {
            // Test database connectivity with a simple query
            DB::connection()->getPdo();

            // Additional test: try a simple SELECT query
            DB::select('SELECT 1');

            return 'healthy';
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Database health check failed: ' . $e->getMessage());
            return 'error';
        }
    }

    private function checkStorageHealth(): string
    {
        try {
            $totalStorageUsed = Upload::sum('size_bytes') ?? 0;

            // Convert to GB for easier checking
            $usedGB = $totalStorageUsed / (1024 * 1024 * 1024);

            // Define storage thresholds (can be configured via env)
            $warningThreshold = (float) config('app.storage_warning_gb', 50); // 50GB default
            $criticalThreshold = (float) config('app.storage_critical_gb', 80); // 80GB default

            if ($usedGB >= $criticalThreshold) {
                return 'critical';
            } elseif ($usedGB >= $warningThreshold) {
                return 'warning';
            }

            return 'healthy';
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Storage health check failed: ' . $e->getMessage());
            return 'error';
        }
    }

    public function refreshDashboard(): void
    {
        $this->dispatch('dashboard-data-refreshed');
    }

    public function loadUsageAnalytics(): void
    {
        // Force refresh of usage analytics data
        $this->dispatch('dashboard-data-refreshed');
    }

    public function render()
    {
        return view('livewire.admin.dashboard', [
            'metrics' => $this->metrics,
            'statusBreakdown' => $this->statusBreakdown,
            'recentActivity' => $this->recentActivity,
            'recentUsers' => $this->recentUsers,
            'systemHealth' => $this->systemHealth,
            'storageMetrics' => $this->storageMetrics,
            'performanceMetrics' => $this->performanceMetrics,
            'trendData' => $this->trendData,
            'usageAnalytics' => $this->usageAnalytics,
        ])->layout('layouts.panel');
    }
}
