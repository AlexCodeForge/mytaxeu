<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\UsageMeteringService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class UsageAnalytics extends Component
{
    public array $systemStats = [];

    public function mount(): void
    {
        Gate::authorize('manage-users');
        $this->loadSystemStats();
    }

    public function loadSystemStats(): void
    {
        $usageMeteringService = app(UsageMeteringService::class);
        $this->systemStats = $usageMeteringService->getSystemUsageStatistics();
    }

    public function render()
    {
        return view('livewire.admin.usage-analytics')
            ->layout('layouts.panel');
    }
}
