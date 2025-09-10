<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\Upload;

class QueueMonitor extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:monitor {connection=redis : The queue connection to monitor}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor queue health and performance metrics for production scaling';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = $this->argument('connection');

        $this->info("🔍 Monitoring Queue System - Connection: {$connection}");
        $this->line("Press Ctrl+C to stop monitoring");
        $this->newLine();

        while (true) {
            $this->clearScreen();
            $this->displayHeader();
            $this->displayQueueStats($connection);
            $this->displaySystemStats();
            $this->displayRecentJobs();
            $this->displayWorkerHealth();

            sleep(5); // Refresh every 5 seconds
        }

        return 0;
    }

    private function clearScreen(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            system('cls');
        } else {
            system('clear');
        }
    }

    private function displayHeader(): void
    {
        $this->info('╔═══════════════════════════════════════════════════════════════╗');
        $this->info('║                    MyTaxEU Queue Monitor                      ║');
        $this->info('║                   ' . now()->format('Y-m-d H:i:s T') . '                    ║');
        $this->info('╚═══════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    private function displayQueueStats(string $connection): void
    {
        try {
            if ($connection === 'redis') {
                $redis = Redis::connection();

                // Get queue lengths
                $defaultQueue = $redis->llen('queues:default');
                $highPriorityQueue = $redis->llen('queues:high-priority');
                $slowQueue = $redis->llen('queues:slow');
                $failedQueue = $redis->llen('queues:failed');

                $this->line('<fg=cyan>📊 Queue Statistics:</fg=cyan>');
                $this->table(
                    ['Queue', 'Pending Jobs', 'Status'],
                    [
                        ['Default', $defaultQueue, $this->getQueueStatus($defaultQueue)],
                        ['High Priority', $highPriorityQueue, $this->getQueueStatus($highPriorityQueue)],
                        ['Slow', $slowQueue, $this->getQueueStatus($slowQueue)],
                        ['Failed', $failedQueue, $failedQueue > 0 ? '<fg=red>⚠️  Attention</fg=red>' : '<fg=green>✅ Good</fg=green>'],
                    ]
                );
            } else {
                // Database queue stats
                $pending = DB::table('jobs')->count();
                $failed = DB::table('failed_jobs')->count();

                $this->line('<fg=cyan>📊 Database Queue Statistics:</fg=cyan>');
                $this->table(
                    ['Type', 'Count', 'Status'],
                    [
                        ['Pending Jobs', $pending, $this->getQueueStatus($pending)],
                        ['Failed Jobs', $failed, $failed > 0 ? '<fg=red>⚠️  Attention</fg=red>' : '<fg=green>✅ Good</fg=green>'],
                    ]
                );
            }
        } catch (\Exception $e) {
            $this->error("Failed to get queue stats: " . $e->getMessage());
        }

        $this->newLine();
    }

    private function displaySystemStats(): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');

        $this->line('<fg=cyan>🖥️  System Resources:</fg=cyan>');
        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Memory Usage', $this->formatBytes($memoryUsage), $this->getMemoryStatus($memoryUsage)],
                ['Peak Memory', $this->formatBytes($memoryPeak), ''],
                ['Memory Limit', $memoryLimit, ''],
                ['Load Average', $this->getLoadAverage(), ''],
            ]
        );
        $this->newLine();
    }

    private function displayRecentJobs(): void
    {
        try {
            $recentUploads = Upload::with('user')
                ->latest()
                ->limit(5)
                ->get(['id', 'user_id', 'original_name', 'status', 'created_at', 'updated_at']);

            $this->line('<fg=cyan>📋 Recent Upload Jobs:</fg=cyan>');

            if ($recentUploads->isEmpty()) {
                $this->line('<fg=yellow>No recent uploads found</fg=yellow>');
            } else {
                $data = $recentUploads->map(function ($upload) {
                    return [
                        'ID' => $upload->id,
                        'User' => $upload->user->name ?? 'Unknown',
                        'File' => \Illuminate\Support\Str::limit($upload->original_name, 30),
                        'Status' => $this->getUploadStatusDisplay($upload->status),
                        'Age' => $upload->created_at->diffForHumans(),
                    ];
                })->toArray();

                $this->table(['ID', 'User', 'File', 'Status', 'Age'], $data);
            }
        } catch (\Exception $e) {
            $this->error("Failed to get recent jobs: " . $e->getMessage());
        }

        $this->newLine();
    }

    private function displayWorkerHealth(): void
    {
        $this->line('<fg=cyan>👷 Worker Health:</fg=cyan>');

        // Check if supervisor is running
        $supervisorStatus = shell_exec('sudo supervisorctl status mytaxeu-worker:* 2>/dev/null') ?: 'Unable to check';

        if (str_contains($supervisorStatus, 'RUNNING')) {
            $this->line('<fg=green>✅ Workers are running</fg=green>');

            // Count running workers
            $runningWorkers = substr_count($supervisorStatus, 'RUNNING');
            $this->line("Active Workers: <fg=green>{$runningWorkers}</fg=green>");
        } else {
            $this->line('<fg=red>❌ Workers may not be running properly</fg=red>');
            $this->line('<fg=yellow>Run: sudo supervisorctl status mytaxeu-worker:*</fg=yellow>');
        }

        $this->newLine();
        $this->line('<fg=blue>💡 Monitoring Tips:</fg=blue>');
        $this->line('• Keep "Pending Jobs" under 100 for optimal performance');
        $this->line('• Monitor failed jobs and investigate causes');
        $this->line('• Ensure workers restart automatically if they crash');
        $this->line('• Scale workers based on queue depth');
    }

    private function getQueueStatus(int $count): string
    {
        if ($count === 0) {
            return '<fg=green>✅ Empty</fg=green>';
        } elseif ($count < 50) {
            return '<fg=green>✅ Good</fg=green>';
        } elseif ($count < 100) {
            return '<fg=yellow>⚠️  Moderate</fg=yellow>';
        } else {
            return '<fg=red>🚨 High</fg=red>';
        }
    }

    private function getMemoryStatus(int $memory): string
    {
        $memoryMB = $memory / 1024 / 1024;

        if ($memoryMB < 100) {
            return '<fg=green>✅ Good</fg=green>';
        } elseif ($memoryMB < 200) {
            return '<fg=yellow>⚠️  Moderate</fg=yellow>';
        } else {
            return '<fg=red>🚨 High</fg=red>';
        }
    }

    private function getUploadStatusDisplay(string $status): string
    {
        return match ($status) {
            'completed' => '<fg=green>✅ Completed</fg=green>',
            'processing' => '<fg=blue>🔄 Processing</fg=blue>',
            'queued' => '<fg=yellow>⏳ Queued</fg=yellow>',
            'failed' => '<fg=red>❌ Failed</fg=red>',
            default => '<fg=gray>' . ucfirst($status) . '</fg=gray>',
        };
    }

    private function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    private function getLoadAverage(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'N/A (Windows)';
        }

        $load = sys_getloadavg();
        return $load ? sprintf('%.2f, %.2f, %.2f', $load[0], $load[1], $load[2]) : 'N/A';
    }
}
