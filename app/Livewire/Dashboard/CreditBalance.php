<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\CreditTransaction;
use App\Services\CreditService;
use App\Services\UsageMeteringService;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class CreditBalance extends Component
{
    use WithPagination;

    public int $creditBalance = 0;
    public int $creditsUsedThisMonth = 0;
    public int $creditsAllocatedThisMonth = 0;
    public bool $showTransactionHistory = false;
    public string $selectedPeriod = '30'; // days

    // Usage metering properties
    public int $linesProcessedThisMonth = 0;
    public int $totalLinesProcessed = 0;
    public int $monthlyLineLimit = 0;
    public float $avgProcessingTimeSeconds = 0;
    public int $processingJobsCompleted = 0;

    protected array $queryString = [
        'selectedPeriod' => ['except' => '30'],
    ];

    protected $listeners = [
        'usageUpdated' => 'refreshUsageData',
        'uploadCompleted' => 'refreshUsageData',
    ];

    public function mount(): void
    {
        $this->loadCreditData();
    }

    public function loadCreditData(): void
    {
        $user = auth()->user();
        $creditService = app(CreditService::class);
        $usageMeteringService = app(UsageMeteringService::class);

        // Get current balance
        $this->creditBalance = $creditService->getCreditBalance($user);

        // Get monthly statistics
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $this->creditsUsedThisMonth = (int) CreditTransaction::where('user_id', $user->id)
            ->where('type', 'consumed')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $this->creditsAllocatedThisMonth = (int) CreditTransaction::where('user_id', $user->id)
            ->where('type', 'purchased')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Get usage metering data
        $this->linesProcessedThisMonth = $usageMeteringService->getCurrentMonthUsage($user);
        $this->totalLinesProcessed = $user->total_lines_processed ?? 0;
        $this->monthlyLineLimit = $usageMeteringService->getMonthlyLimit($user);

        // Get usage statistics
        $usageStats = $usageMeteringService->getUserUsageStatistics($user);
        $this->avgProcessingTimeSeconds = $usageStats['avg_processing_time_seconds'] ?? 0.0;
        $this->processingJobsCompleted = $usageStats['total_jobs_completed'] ?? 0;
    }

    public function toggleTransactionHistory(): void
    {
        $this->showTransactionHistory = !$this->showTransactionHistory;
        if ($this->showTransactionHistory) {
            $this->resetPage();
        }
    }

    public function updatedSelectedPeriod(): void
    {
        $this->resetPage();
    }

    public function refreshUsageData(): void
    {
        $this->loadCreditData();
    }

    public function getRecentTransactionsProperty(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $user = auth()->user();
        $days = (int) $this->selectedPeriod;

        return CreditTransaction::where('user_id', $user->id)
            ->with(['upload', 'subscription'])
            ->when($days > 0, function ($query) use ($days) {
                return $query->where('created_at', '>=', now()->subDays($days));
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                    'upload_name' => $transaction->upload?->original_name,
                    'subscription_id' => $transaction->subscription?->stripe_id,
                ];
            });
    }

    public function getTransactionTypeIconProperty(): callable
    {
        return function (string $type): string {
            return match ($type) {
                'purchased' => '↗',
                'consumed' => '↘',
                'refunded' => '↩',
                'expired' => '⚠',
                default => '•',
            };
        };
    }

    public function getTransactionTypeClassProperty(): callable
    {
        return function (string $type): string {
            return match ($type) {
                'purchased' => 'text-green-600 bg-green-50',
                'consumed' => 'text-red-600 bg-red-50',
                'refunded' => 'text-blue-600 bg-blue-50',
                'expired' => 'text-yellow-600 bg-yellow-50',
                default => 'text-gray-600 bg-gray-50',
            };
        };
    }

    public function getTransactionTypeNameProperty(): callable
    {
        return function (string $type): string {
            return match ($type) {
                'purchased' => 'Comprados',
                'consumed' => 'Consumidos',
                'refunded' => 'Reembolsados',
                'expired' => 'Expirados',
                default => 'Desconocido',
            };
        };
    }

    public function getUsagePercentageProperty(): float
    {
        if ($this->creditsAllocatedThisMonth <= 0) {
            return 0;
        }

        return min(100, abs($this->creditsUsedThisMonth) / $this->creditsAllocatedThisMonth * 100);
    }

    public function getLineUsagePercentageProperty(): float
    {
        if ($this->monthlyLineLimit <= 0) {
            return 0;
        }

        return min(100, ($this->linesProcessedThisMonth / $this->monthlyLineLimit) * 100);
    }

    public function getFormattedProcessingTimeProperty(): string
    {
        if ($this->avgProcessingTimeSeconds <= 0) {
            return '0s';
        }

        if ($this->avgProcessingTimeSeconds < 60) {
            return round($this->avgProcessingTimeSeconds, 1) . 's';
        }

        $minutes = floor($this->avgProcessingTimeSeconds / 60);
        $seconds = round($this->avgProcessingTimeSeconds % 60);

        return $minutes . 'm ' . $seconds . 's';
    }

    public function getSubscriptionStatusProperty(): ?array
    {
        $user = auth()->user();

        if (!$user->subscribed()) {
            return null;
        }

        $subscription = $user->subscription();
        $stripeSubscription = $subscription->asStripeSubscription();

        return [
            'status' => $subscription->stripe_status,
            'current_period_end' => $stripeSubscription->current_period_end,
            'cancel_at_period_end' => $stripeSubscription->cancel_at_period_end ?? false,
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.credit-balance');
    }
}
