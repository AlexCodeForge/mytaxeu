<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\UsageMeteringService;
use Livewire\Component;

class UsageStats extends Component
{
    public array $statistics = [];
    public array $trends = [];
    public int $trendDays = 7;

    protected $listeners = [
        'usageUpdated' => 'refreshStats',
        'uploadCompleted' => 'refreshStats',
    ];

    public function mount(): void
    {
        $this->loadStatistics();
        $this->loadTrends();
    }

    public function refreshStats(): void
    {
        $this->loadStatistics();
        $this->loadTrends();
    }

    public function loadStatistics(): void
    {
        $user = auth()->user();
        $usageMeteringService = app(UsageMeteringService::class);

        $this->statistics = $usageMeteringService->getUserUsageStatistics($user);
    }

    public function loadTrends(): void
    {
        $user = auth()->user();
        $usageMeteringService = app(UsageMeteringService::class);

        $this->trends = $usageMeteringService->getUsageTrends($user, $this->trendDays);
    }

    public function updateTrendDays(int $days): void
    {
        $this->trendDays = $days;
        $this->loadTrends();
    }

    public function getFormattedProcessingTime(): string
    {
        $seconds = $this->statistics['average_processing_time'] ?? 0;

        if ($seconds <= 0) {
            return '0s';
        }

        if ($seconds < 60) {
            return round($seconds, 1) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds % 60);

        return $minutes . 'm ' . $remainingSeconds . 's';
    }

    public function getFormattedFileSize(): string
    {
        $bytes = $this->statistics['average_file_size'] ?? 0;

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 0) . ' ' . $units[$unitIndex];
    }

    public function getSuccessRate(): float
    {
        $total = $this->statistics['total_uploads'] ?? 0;
        $successful = $this->statistics['successful_uploads'] ?? 0;

        if ($total <= 0) {
            return 0.0;
        }

        return round(($successful / $total) * 100, 1);
    }

    public function getTrendChange(): array
    {
        if (count($this->trends) < 2) {
            return ['value' => 0, 'direction' => 'stable'];
        }

        // Compare most recent day with previous day
        $recent = $this->trends[0]['line_count'] ?? 0;
        $previous = $this->trends[1]['line_count'] ?? 0;

        if ($previous == 0) {
            return ['value' => 0, 'direction' => 'stable'];
        }

        $change = (($recent - $previous) / $previous) * 100;

        return [
            'value' => round(abs($change), 1),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
        ];
    }

    public function getTotalTrendLines(): int
    {
        return array_sum(array_column($this->trends, 'line_count'));
    }

    public function hasData(): bool
    {
        return ($this->statistics['total_uploads'] ?? 0) > 0;
    }

    public function render()
    {
        return view('livewire.dashboard.usage-stats');
    }
}
