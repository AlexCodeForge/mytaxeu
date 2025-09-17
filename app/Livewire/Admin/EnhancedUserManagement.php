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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EnhancedUserManagement extends Component
{
    use WithPagination, AuthorizesRequests;

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

    // Credit management
    public bool $showCreditsModal = false;
    public int $creditsChange = 0;
    public string $creditsOperation = 'add'; // add or subtract

    // Suspension management
    public bool $showSuspensionModal = false;
    public string $suspensionReason = '';
    public ?int $userToSuspend = null;

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
            abort(403, 'Acceso denegado');
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
        $this->loadUserStats();
        $this->loadUserActivity();
        $this->showUserModal = true;
    }

    public function closeUserModal(): void
    {
        $this->showUserModal = false;
        $this->selectedUserId = null;
        $this->selectedUser = null;
        $this->userStats = [];
        $this->userActivity = [];
    }

    public function openSuspensionModal(int $userId): void
    {
        logger()->info('🚨 MODAL DEBUG: openSuspensionModal called', [
            'userId' => $userId,
            'admin_id' => Auth::id(),
        ]);

        if ($userId === Auth::id()) {
            logger()->warning('🚨 MODAL WARNING: Admin trying to suspend themselves');
            $this->dispatch('show-notification', [
                'message' => 'No puedes suspenderte a ti mismo',
                'type' => 'error',
            ]);
            return;
        }

        $user = User::findOrFail($userId);

        if ($user->isAdmin()) {
            logger()->warning('🚨 MODAL WARNING: Trying to suspend admin user');
            $this->dispatch('show-notification', [
                'message' => 'Los usuarios administradores no pueden ser suspendidos',
                'type' => 'error',
            ]);
            return;
        }

        $this->userToSuspend = $userId;
        $this->suspensionReason = '';
        $this->showSuspensionModal = true;

        logger()->info('🚨 MODAL DEBUG: Modal opened successfully', [
            'userToSuspend' => $this->userToSuspend,
            'showSuspensionModal' => $this->showSuspensionModal,
        ]);
    }

    public function closeSuspensionModal(): void
    {
        $this->showSuspensionModal = false;
        $this->userToSuspend = null;
        $this->suspensionReason = '';
        $this->resetErrorBag();
    }

    public function confirmSuspendUser(): void
    {
        logger()->info('🚨 SUSPENSION DEBUG: confirmSuspendUser called', [
            'userToSuspend' => $this->userToSuspend,
            'admin_id' => Auth::id(),
        ]);

        if (!$this->userToSuspend) {
            logger()->error('🚨 SUSPENSION ERROR: No userToSuspend found');
            $this->addError('user', 'Usuario no encontrado.');
            return;
        }

        try {
            $user = User::findOrFail($this->userToSuspend);

            logger()->info('🚨 SUSPENSION DEBUG: User found', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'current_suspension_status' => $user->is_suspended,
            ]);

            $updateData = [
                'is_suspended' => true,
                'suspended_at' => now(),
                'suspended_by' => Auth::id(),
                'suspension_reason' => $this->suspensionReason ?: 'Suspendido por administrador',
            ];

            logger()->info('🚨 SUSPENSION DEBUG: About to update user with data', $updateData);

            $updateResult = $user->update($updateData);

            logger()->info('🚨 SUSPENSION DEBUG: Update result', [
                'update_success' => $updateResult,
                'user_after_update' => $user->fresh()->only(['id', 'email', 'is_suspended', 'suspended_at', 'suspended_by']),
            ]);

            $this->logAdminAction('user_suspended', $this->userToSuspend, "Usuario {$user->email} suspendido" . ($this->suspensionReason ? ". Motivo: {$this->suspensionReason}" : ""));

            $this->dispatch('show-notification', [
                'message' => "El usuario {$user->name} ha sido suspendido exitosamente",
                'type' => 'success',
            ]);

            if ($this->selectedUserId === $this->userToSuspend) {
                $this->selectedUser = $user->fresh();
            }

            $this->closeSuspensionModal();

            logger()->info('🚨 SUSPENSION DEBUG: Process completed successfully');

        } catch (\Exception $e) {
            logger()->error('🚨 SUSPENSION ERROR: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->dispatch('show-notification', [
                'message' => 'Error al suspender usuario: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    public function suspendUser(int $userId): void
    {
        // Keep this method for backward compatibility, but redirect to modal
        $this->openSuspensionModal($userId);
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

        $this->logAdminAction('user_activated', $userId, "Usuario {$user->email} activado");

        $this->dispatch('show-notification', [
            'message' => "El usuario {$user->name} ha sido activado exitosamente",
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
                'message' => 'Los usuarios administradores no pueden ser suspendidos. Solo se suspendieron los usuarios regulares.',
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

            $this->logAdminAction('user_suspended', $user->id, "Suspensión masiva del usuario {$user->email}");
            $suspendedCount++;
        }

        $this->dispatch('show-notification', [
            'message' => "{$suspendedCount} usuarios suspendidos exitosamente",
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

            $this->logAdminAction('user_activated', $user->id, "Activación masiva del usuario {$user->email}");
        }

        $this->dispatch('show-notification', [
            'message' => count($this->selectedUsers) . " usuarios activados exitosamente",
            'type' => 'success',
        ]);

        $this->selectedUsers = [];
    }

    public function openCreditsModal(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->selectedUser = User::findOrFail($userId);
        $this->creditsChange = 0;
        $this->creditsOperation = 'add';
        $this->showCreditsModal = true;
    }

    public function closeCreditsModal(): void
    {
        $this->showCreditsModal = false;
        $this->selectedUserId = null;
        $this->creditsChange = 0;
        $this->creditsOperation = 'add';
        $this->resetErrorBag();
    }

    public function updateCredits(): void
    {
        $this->validate([
            'creditsChange' => 'required|integer|min:1',
            'creditsOperation' => 'required|in:add,subtract',
        ], [
            'creditsChange.required' => 'La cantidad de créditos es obligatoria.',
            'creditsChange.integer' => 'La cantidad debe ser un número entero.',
            'creditsChange.min' => 'La cantidad debe ser mayor a 0.',
            'creditsOperation.required' => 'La operación es obligatoria.',
            'creditsOperation.in' => 'La operación debe ser agregar o quitar.',
        ]);

        if (!$this->selectedUser) {
            $this->addError('user', 'Usuario no encontrado.');
            return;
        }

        $newCredits = $this->creditsOperation === 'add'
            ? $this->selectedUser->credits + $this->creditsChange
            : $this->selectedUser->credits - $this->creditsChange;

        if ($newCredits < 0) {
            $this->addError('creditsChange', 'No se pueden quitar más créditos de los disponibles.');
            return;
        }

        // Update user credits and create transaction record
        $this->selectedUser->update(['credits' => $newCredits]);

        // Create proper credit transaction for tracking
        \App\Models\CreditTransaction::create([
            'user_id' => $this->selectedUser->id,
            'type' => $this->creditsOperation === 'add' ? 'purchased' : 'consumed',
            'amount' => $this->creditsOperation === 'add' ? $this->creditsChange : -$this->creditsChange,
            'description' => 'Ajuste manual por administrador: ' .
                           ($this->creditsOperation === 'add' ? 'agregados' : 'removidos') .
                           " {$this->creditsChange} créditos",
        ]);

        logger()->info('Cambio de créditos por administrador', [
            'admin_id' => auth()->id(),
            'user_id' => $this->selectedUser->id,
            'operation' => $this->creditsOperation,
            'amount' => $this->creditsChange,
            'old_credits' => $this->selectedUser->credits - ($this->creditsOperation === 'add' ? $this->creditsChange : -$this->creditsChange),
            'new_credits' => $newCredits,
        ]);

        $this->dispatch('show-notification', [
            'message' => "Créditos actualizados correctamente para {$this->selectedUser->name}.",
            'type' => 'success',
        ]);

        $this->closeCreditsModal();

        // Refresh user data if modal is open
        if ($this->showUserModal) {
            $this->selectedUser = $this->selectedUser->fresh();
        }
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
