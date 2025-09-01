<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Services\UsageMeteringService;
use Livewire\Component;

class UsageDashboard extends Component
{
    public int $currentMonthUsage = 0;
    public int $totalLinesProcessed = 0;
    public int $monthlyLimit = 0;
    public int $remainingLimit = 0;
    public float $usagePercentage = 0.0;
    public int $totalUploads = 0;
    public int $successfulUploads = 0;
    public int $failedUploads = 0;
    public float $averageProcessingTime = 0.0;
    public int $totalCreditsConsumed = 0;
    public float $averageFileSize = 0.0;

    protected $listeners = [
        'usageUpdated' => 'refreshUsageData',
        'uploadCompleted' => 'refreshUsageData',
    ];

    public function mount(): void
    {
        $this->loadUsageData();
    }

    public function refreshUsageData(): void
    {
        $this->loadUsageData();
    }

    public function loadUsageData(): void
    {
        $user = auth()->user();
        $usageMeteringService = app(UsageMeteringService::class);

        $statistics = $usageMeteringService->getUserUsageStatistics($user);

        $this->currentMonthUsage = $statistics['current_month_usage'];
        $this->totalLinesProcessed = $statistics['total_lines_processed'];
        $this->monthlyLimit = $usageMeteringService->getMonthlyLimit($user);
        $this->remainingLimit = $statistics['remaining_monthly_limit'];
        $this->totalUploads = $statistics['total_uploads'];
        $this->successfulUploads = $statistics['successful_uploads'];
        $this->failedUploads = $statistics['failed_uploads'];
        $this->averageProcessingTime = $statistics['average_processing_time'];
        $this->totalCreditsConsumed = $statistics['total_credits_consumed'];
        $this->averageFileSize = $statistics['average_file_size'];

        // Calculate usage percentage
        $this->usagePercentage = $this->monthlyLimit > 0
            ? round(($this->currentMonthUsage / $this->monthlyLimit) * 100, 1)
            : 0.0;
    }

    public function getUsageColorClass(): string
    {
        if ($this->usagePercentage >= 90) {
            return 'bg-red-600';
        } elseif ($this->usagePercentage >= 75) {
            return 'bg-yellow-500';
        } else {
            return 'bg-blue-600';
        }
    }

    public function getUsageStatusText(): string
    {
        if ($this->usagePercentage >= 100) {
            return 'Límite excedido';
        } elseif ($this->usagePercentage >= 90) {
            return 'Cerca del límite';
        } elseif ($this->usagePercentage >= 75) {
            return 'Uso elevado';
        } else {
            return 'Uso normal';
        }
    }

    public function getFormattedProcessingTime(): string
    {
        if ($this->averageProcessingTime <= 0) {
            return '0s';
        }

        if ($this->averageProcessingTime < 60) {
            return round($this->averageProcessingTime, 1) . 's';
        }

        $minutes = floor($this->averageProcessingTime / 60);
        $seconds = round($this->averageProcessingTime % 60);

        return $minutes . 'm ' . $seconds . 's';
    }

    public function getFormattedFileSize(): string
    {
        if ($this->averageFileSize <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->averageFileSize;
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 0) . ' ' . $units[$unitIndex];
    }

    public function render()
    {
        return view('livewire.dashboard.usage-dashboard');
    }
}
