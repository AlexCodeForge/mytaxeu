<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\Upload;
use App\Models\UploadMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class EnhancedUserManagement extends Component
{
    use WithPagination;

    public string $search = '';
    public string $activityFilter = 'all'; // all, active, inactive
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';
    public string $dateFrom = '';
    public string $dateTo = '';

    public bool $showUserModal = false;
    public ?int $selectedUserId = null;
    public array $selectedUsers = [];
    public bool $bulkMode = false;

    // User profile data
    public ?User $selectedUser = null;
    public array $userStats = [];
    public array $userActivity = [];
    public array $userLoginHistory = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'activityFilter' => ['except' => 'all'],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }
    }

    public function render()
    {
        $users = $this->getUsersQuery()->paginate(20);

        return view('livewire.admin.enhanced-user-management', [
            'users' => $users,
        ])->layout('layouts.panel');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedActivityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function openUserProfile(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->selectedUser = User::with(['uploads', 'uploadMetrics'])->findOrFail($userId);
        $this->loadUserData();
        $this->showUserModal = true;
    }

    public function closeUserModal(): void
    {
        $this->showUserModal = false;
        $this->selectedUserId = null;
        $this->selectedUser = null;
        $this->userStats = [];
        $this->userActivity = [];
        $this->userLoginHistory = [];
    }

    public function suspendUser(int $userId): void
    {
        if ($userId === Auth::id()) {
            $this->dispatch('show-notification', [
                'message' => 'You cannot suspend yourself',
                'type' => 'error',
            ]);
            return;
        }

        $user = User::findOrFail($userId);

        if ($user->isAdmin()) {
            $this->dispatch('show-notification', [
                'message' => 'Admin users cannot be suspended',
                'type' => 'error',
            ]);
            return;
        }

        $user->update([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspended_by' => Auth::id(),
        ]);

        $this->logAdminAction('user_suspended', $userId, "User {$user->email} suspended");

        $this->dispatch('show-notification', [
            'message' => "User {$user->name} has been suspended successfully",
            'type' => 'success',
        ]);

        if ($this->selectedUserId === $userId) {
            $this->selectedUser = $user->fresh();
        }
    }

    public function activateUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        $user->update([
            'is_suspended' => false,
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);

        $this->logAdminAction('user_activated', $userId, "User {$user->email} activated");

        $this->dispatch('show-notification', [
            'message' => "User {$user->name} has been activated successfully",
            'type' => 'success',
        ]);

        if ($this->selectedUserId === $userId) {
            $this->selectedUser = $user->fresh();
        }
    }

    public function toggleBulkMode(): void
    {
        $this->bulkMode = !$this->bulkMode;
        $this->selectedUsers = [];
    }

    public function toggleUserSelection(int $userId): void
    {
        if (in_array($userId, $this->selectedUsers)) {
            $this->selectedUsers = array_diff($this->selectedUsers, [$userId]);
        } else {
            $this->selectedUsers[] = $userId;
        }
    }

    public function selectAllUsers(): void
    {
        $userIds = $this->getUsersQuery()->pluck('id')->toArray();
        $this->selectedUsers = $userIds;
    }

    public function clearSelection(): void
    {
        $this->selectedUsers = [];
    }

    public function bulkSuspendUsers(): void
    {
        if (empty($this->selectedUsers)) {
            return;
        }

        $users = User::whereIn('id', $this->selectedUsers)->get();
        $adminUsers = $users->where('is_admin', true);
        $regularUsers = $users->where('is_admin', false);

        if ($adminUsers->isNotEmpty()) {
            $this->dispatch('show-notification', [
                'message' => 'Admin users cannot be suspended. Only regular users were suspended.',
                'type' => 'warning',
            ]);
        }

        $suspendedCount = 0;
        foreach ($regularUsers as $user) {
            $user->update([
                'is_suspended' => true,
                'suspended_at' => now(),
                'suspended_by' => Auth::id(),
            ]);

            $this->logAdminAction('user_suspended', $user->id, "Bulk suspension of user {$user->email}");
            $suspendedCount++;
        }

        $this->dispatch('show-notification', [
            'message' => "{$suspendedCount} users suspended successfully",
            'type' => 'success',
        ]);

        $this->selectedUsers = [];
    }

    public function bulkActivateUsers(): void
    {
        if (empty($this->selectedUsers)) {
            return;
        }

        $users = User::whereIn('id', $this->selectedUsers)->get();

        foreach ($users as $user) {
            $user->update([
                'is_suspended' => false,
                'suspended_at' => null,
                'suspended_by' => null,
                'suspension_reason' => null,
            ]);

            $this->logAdminAction('user_activated', $user->id, "Bulk activation of user {$user->email}");
        }

        $this->dispatch('show-notification', [
            'message' => count($this->selectedUsers) . " users activated successfully",
            'type' => 'success',
        ]);

        $this->selectedUsers = [];
    }

    public function exportUserActivity(int $userId): void
    {
        $this->dispatch('start-export', [
            'type' => 'users',
            'filters' => ['user_id' => $userId],
        ]);
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->activityFilter = 'all';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    private function getUsersQuery(): Builder
    {
        $query = User::query()
            ->withCount(['uploads'])
            ->with(['uploads' => function ($query) {
                $query->latest()->limit(5);
            }]);

        // Search filter
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        // Activity filter
        if ($this->activityFilter === 'active') {
            $query->has('uploads');
        } elseif ($this->activityFilter === 'inactive') {
            $query->doesntHave('uploads');
        }

        // Date range filter
        if (!empty($this->dateFrom)) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if (!empty($this->dateTo)) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        // Sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query;
    }

    private function loadUserData(): void
    {
        if (!$this->selectedUser) {
            return;
        }

        $this->loadUserStats();
        $this->loadUserActivity();
        $this->loadUserLoginHistory();
    }

    private function loadUserStats(): void
    {
        $user = $this->selectedUser;

        $totalUploads = $user->uploads->count();
        $completedUploads = $user->uploads->where('status', Upload::STATUS_COMPLETED)->count();
        $failedUploads = $user->uploads->where('status', Upload::STATUS_FAILED)->count();

        $totalSize = $user->uploads->sum('size_bytes');
        $totalCredits = $user->uploadMetrics->sum('credits_consumed');
        $avgProcessingTime = $user->uploadMetrics->avg('processing_duration_seconds') ?? 0;

        $successRate = $totalUploads > 0 ? round(($completedUploads / $totalUploads) * 100, 1) : 0;

        // Calculate engagement rate (uploads per day since registration)
        $daysSinceRegistration = $user->created_at->diffInDays(now()) ?: 1;
        $engagementRate = round(($totalUploads / $daysSinceRegistration) * 100, 1);

        $this->userStats = [
            'total_uploads' => $totalUploads,
            'completed_uploads' => $completedUploads,
            'failed_uploads' => $failedUploads,
            'success_rate' => $successRate,
            'total_size' => $this->formatBytes($totalSize),
            'total_size_bytes' => $totalSize,
            'total_credits' => $totalCredits,
            'avg_processing_time' => $this->formatDuration($avgProcessingTime),
            'avg_processing_time_seconds' => $avgProcessingTime,
            'days_since_registration' => $daysSinceRegistration,
            'engagement_rate' => $engagementRate,
            'last_activity' => $user->uploads->max('created_at'),
        ];
    }

    private function loadUserActivity(): void
    {
        $this->userActivity = $this->selectedUser->uploads()
            ->with(['uploadMetric'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($upload) {
                return [
                    'id' => $upload->id,
                    'filename' => $upload->original_name,
                    'status' => $upload->status,
                    'size' => $upload->formatted_size,
                    'created_at' => $upload->created_at,
                    'processed_at' => $upload->processed_at,
                    'failure_reason' => $upload->failure_reason,
                    'processing_time' => $upload->uploadMetric?->processing_duration_seconds,
                    'credits_consumed' => $upload->uploadMetric?->credits_consumed,
                ];
            })
            ->toArray();
    }

    private function loadUserLoginHistory(): void
    {
        $this->userLoginHistory = DB::table('user_login_logs')
            ->where('user_id', $this->selectedUser->id)
            ->orderBy('logged_in_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'logged_in_at' => $log->logged_in_at,
                    'logged_out_at' => $log->logged_out_at,
                    'successful' => $log->successful,
                ];
            })
            ->toArray();
    }

    private function logAdminAction(string $action, ?int $targetUserId = null, string $details = ''): void
    {
        DB::table('admin_action_logs')->insert([
            'admin_user_id' => Auth::id(),
            'action' => $action,
            'target_user_id' => $targetUserId,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

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

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds) . 's';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return $minutes . ':' . str_pad((string) round($remainingSeconds), 2, '0', STR_PAD_LEFT);
    }
}
