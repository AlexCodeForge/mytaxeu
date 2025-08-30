<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class CreditAnalytics extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public string $sortBy = 'credits';
    public string $sortDirection = 'desc';
    public string $selectedPeriod = '30'; // days
    public bool $showZeroBalances = true;

    protected array $queryString = [
        'search' => ['except' => ''],
        'sortBy' => ['except' => 'credits'],
        'sortDirection' => ['except' => 'desc'],
        'selectedPeriod' => ['except' => '30'],
        'showZeroBalances' => ['except' => true],
    ];

    public function mount(): void
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Access denied. Admin privileges required.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedShowZeroBalances(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }
        $this->resetPage();
    }

    public function getUsersWithCreditsProperty(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when(!$this->showZeroBalances, function ($query) {
                $query->where('credits', '>', 0);
            })
            ->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate(15);
    }

    public function getCreditStatisticsProperty(): array
    {
        $totalUsers = User::count();
        $usersWithCredits = User::where('credits', '>', 0)->count();
        $totalCreditsInCirculation = User::sum('credits');
        $usersWithSubscriptions = User::whereHas('subscriptions', function ($query) {
            $query->where('stripe_status', 'active');
        })->count();

        // Get period-based statistics
        $days = (int) $this->selectedPeriod;
        $startDate = $days > 0 ? now()->subDays($days) : null;

        $creditsAllocated = CreditTransaction::where('type', 'purchased')
            ->when($startDate, function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            })
            ->sum('amount');

        $creditsConsumed = abs(CreditTransaction::where('type', 'consumed')
            ->when($startDate, function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            })
            ->sum('amount'));

        return [
            'total_users' => $totalUsers,
            'users_with_credits' => $usersWithCredits,
            'total_credits_in_circulation' => $totalCreditsInCirculation,
            'users_with_subscriptions' => $usersWithSubscriptions,
            'credits_allocated_period' => $creditsAllocated,
            'credits_consumed_period' => $creditsConsumed,
            'period_days' => $days,
        ];
    }

    public function getTopCreditUsersProperty(): Collection
    {
        return User::where('credits', '>', 0)
            ->orderBy('credits', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'user' => $user,
                    'credits' => $user->credits,
                    'recent_transactions' => $user->creditTransactions()
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count(),
                ];
            });
    }

    public function getCreditTransactionTrendsProperty(): array
    {
        $days = 7; // Last 7 days
        $trends = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dayStart = $date->startOfDay();
            $dayEnd = $date->endOfDay();

            $allocated = CreditTransaction::where('type', 'purchased')
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->sum('amount');

            $consumed = abs(CreditTransaction::where('type', 'consumed')
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->sum('amount'));

            $trends[] = [
                'date' => $date->format('d/m'),
                'allocated' => $allocated,
                'consumed' => $consumed,
            ];
        }

        return $trends;
    }

    public function allocateCreditsToUser(int $userId, int $amount, string $description = ''): void
    {
        try {
            $user = User::findOrFail($userId);
            $creditService = app(CreditService::class);

            $description = $description ?: "Asignación manual por administrador";

            $success = $creditService->allocateCredits($user, $amount, $description);

            if ($success) {
                session()->flash('message', "Se asignaron {$amount} créditos a {$user->name} exitosamente.");
            } else {
                session()->flash('error', 'Error al asignar créditos.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.credit-analytics')->layout('layouts.panel');
    }
}
