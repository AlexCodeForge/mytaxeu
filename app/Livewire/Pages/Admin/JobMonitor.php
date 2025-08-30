<?php

declare(strict_types=1);

namespace App\Livewire\Pages\Admin;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class JobMonitor extends Component
{
    /**
     * Mount the component and check admin permissions.
     */
    public function mount(): void
    {
        // Ensure user is admin
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }
    }

    public function render()
    {
        return view('livewire.pages.admin.job-monitor')
            ->layout('layouts.panel')
            ->layoutData(['title' => 'Monitoreo de Trabajos']);
    }
}
