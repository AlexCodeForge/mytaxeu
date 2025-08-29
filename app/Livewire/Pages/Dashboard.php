<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Models\Upload;
use Livewire\Component;

class Dashboard extends Component
{
    public function getUserStats(): array
    {
        $user = auth()->user();

        $totalUploads = Upload::where('user_id', $user->id)->count();
        $completedUploads = Upload::where('user_id', $user->id)
            ->where('status', Upload::STATUS_COMPLETED)->count();
        $processingUploads = Upload::where('user_id', $user->id)
            ->whereIn('status', [Upload::STATUS_QUEUED, Upload::STATUS_PROCESSING])->count();
        $failedUploads = Upload::where('user_id', $user->id)
            ->where('status', Upload::STATUS_FAILED)->count();

        $successRate = $totalUploads > 0 ? round(($completedUploads / $totalUploads) * 100, 1) : 0;

        return [
            'totalUploads' => $totalUploads,
            'completedUploads' => $completedUploads,
            'processingUploads' => $processingUploads,
            'failedUploads' => $failedUploads,
            'successRate' => $successRate,
            'credits' => $user->credits ?? 0,
        ];
    }

    public function getRecentUploads()
    {
        return Upload::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.pages.dashboard', [
            'stats' => $this->getUserStats(),
            'recentUploads' => $this->getRecentUploads(),
        ])->layout('layouts.panel');
    }
}


