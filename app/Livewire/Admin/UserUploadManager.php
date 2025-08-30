<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\UserUploadLimit;
use App\Models\AdminActionLog;
use App\Models\IpUploadTracking;
use App\Services\UploadLimitValidator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class UserUploadManager extends Component
{
    use WithPagination, AuthorizesRequests;

    public string $search = '';
    public string $sortBy = 'name';
    public string $sortDirection = 'asc';
    public int $perPage = 10;

    // Modal state
    public bool $showLimitModal = false;
    public ?User $selectedUser = null;
    public int $newLimit = 100;
    public string $expirationDate = '';
    public string $notes = '';

    // Statistics view
    public array $uploadStats = [];

    protected array $rules = [
        'newLimit' => 'required|integer|min:1|max:10000',
        'expirationDate' => 'nullable|date|after:today',
        'notes' => 'nullable|string|max:500',
    ];

    protected array $messages = [
        'newLimit.required' => 'El límite es obligatorio.',
        'newLimit.integer' => 'El límite debe ser un número entero.',
        'newLimit.min' => 'El límite debe ser al menos 1.',
        'newLimit.max' => 'El límite no puede ser mayor a 10,000.',
        'expirationDate.date' => 'La fecha debe ser válida.',
        'expirationDate.after' => 'La fecha debe ser posterior a hoy.',
        'notes.max' => 'Las notas no pueden tener más de 500 caracteres.',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
        $this->loadUploadStats();
    }

    public function updatingSearch(): void
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

    public function openLimitModal(User $user): void
    {
        $this->selectedUser = $user;
        $this->showLimitModal = true;

        // Pre-fill with current limit if exists
        $currentLimit = app(UploadLimitValidator::class)->getUserLimit($user);
        if ($currentLimit) {
            $this->newLimit = $currentLimit->csv_line_limit;
            $this->expirationDate = $currentLimit->expires_at?->format('Y-m-d') ?? '';
        } else {
            $this->newLimit = 100;
            $this->expirationDate = '';
        }
        
        $this->notes = '';
    }

    public function closeLimitModal(): void
    {
        $this->showLimitModal = false;
        $this->selectedUser = null;
        $this->reset(['newLimit', 'expirationDate', 'notes']);
        $this->resetErrorBag();
    }

    public function setUserLimit(): void
    {
        $this->validate();

        if (!$this->selectedUser) {
            return;
        }

        $validator = app(UploadLimitValidator::class);
        $oldLimit = $validator->getUserLimit($this->selectedUser);

        // Create new limit
        $newLimitRecord = UserUploadLimit::create([
            'user_id' => $this->selectedUser->id,
            'csv_line_limit' => $this->newLimit,
            'expires_at' => $this->expirationDate ? now()->parse($this->expirationDate) : null,
            'created_by' => auth()->id(),
        ]);

        // Log the admin action
        AdminActionLog::logAction(
            auth()->id(),
            'limit_override',
            [
                'target_user_id' => $this->selectedUser->id,
                'old_value' => $oldLimit ? [
                    'csv_line_limit' => $oldLimit->csv_line_limit,
                    'expires_at' => $oldLimit->expires_at,
                ] : null,
                'new_value' => [
                    'csv_line_limit' => $this->newLimit,
                    'expires_at' => $this->expirationDate ?: null,
                ],
                'notes' => $this->notes ?: null,
            ]
        );

        $this->dispatch('flash-message', [
            'type' => 'success',
            'message' => "Límite actualizado para {$this->selectedUser->name}: {$this->newLimit} líneas"
        ]);

        $this->closeLimitModal();
    }

    public function resetUserLimit(User $user): void
    {
        $validator = app(UploadLimitValidator::class);
        $currentLimit = $validator->getUserLimit($user);

        if ($currentLimit) {
            // Log the reset action
            AdminActionLog::logAction(
                auth()->id(),
                'limit_reset',
                [
                    'target_user_id' => $user->id,
                    'old_value' => [
                        'csv_line_limit' => $currentLimit->csv_line_limit,
                        'expires_at' => $currentLimit->expires_at,
                    ],
                    'new_value' => null,
                    'notes' => 'Límite restablecido a valor por defecto',
                ]
            );

            // Delete current limit (user will fall back to default)
            $currentLimit->delete();

            $this->dispatch('flash-message', [
                'type' => 'success',
                'message' => "Límite restablecido para {$user->name} (100 líneas por defecto)"
            ]);
        }
    }

    public function loadUploadStats(): void
    {
        $this->uploadStats = [
            'total_users' => User::count(),
            'users_with_custom_limits' => UserUploadLimit::distinct('user_id')->count(),
            'total_uploads_today' => \App\Models\Upload::whereDate('created_at', today())->count(),
            'total_ip_tracking_records' => IpUploadTracking::count(),
            'recent_admin_actions' => AdminActionLog::with('adminUser', 'targetUser')
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->with(['uploads' => function ($query) {
                $query->select('user_id')
                    ->selectRaw('COUNT(*) as upload_count')
                    ->selectRaw('SUM(csv_line_count) as total_lines')
                    ->groupBy('user_id');
            }, 'uploadLimits' => function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })->latest();
            }])
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.user-upload-manager', [
            'users' => $users,
            'uploadStats' => $this->uploadStats,
        ])->layout('layouts.panel');
    }
}
