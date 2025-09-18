<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\CreditTransaction;
use App\Services\FinancialDataService;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

#[Layout('layouts.panel')]
class FinancialDashboard extends Component
{
    use WithPagination;
    public string $timePeriod = 'monthly';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public bool $loading = false;
    public bool $hasError = false;
    public int $perPage = 10;

    protected ?FinancialDataService $financialService = null;

    protected $queryString = [
        'timePeriod' => ['except' => 'monthly'],
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'perPage' => ['except' => 10],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        // Ensure user is admin
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        $this->financialService = app(FinancialDataService::class);

        Log::debug('FinancialDashboard: Component mounted', [
            'user_id' => auth()->id(),
            'time_period' => $this->timePeriod,
        ]);
    }

    /**
     * Get or initialize the financial service.
     */
    protected function getFinancialService(): FinancialDataService
    {
        if ($this->financialService === null) {
            $this->financialService = app(FinancialDataService::class);
        }

        return $this->financialService;
    }

    public function rules(): array
    {
        return [
            'timePeriod' => ['required', 'string', 'in:monthly,quarterly,yearly,custom'],
            'startDate' => ['nullable', 'date', 'required_if:timePeriod,custom'],
            'endDate' => ['nullable', 'date', 'required_if:timePeriod,custom', 'after_or_equal:startDate'],
        ];
    }

    public function messages(): array
    {
        return [
            'timePeriod.required' => 'El período de tiempo es obligatorio.',
            'timePeriod.in' => 'El período de tiempo seleccionado no es válido.',
            'startDate.required_if' => 'La fecha de inicio es obligatoria para el rango personalizado.',
            'startDate.date' => 'La fecha de inicio debe ser una fecha válida.',
            'endDate.required_if' => 'La fecha de fin es obligatoria para el rango personalizado.',
            'endDate.date' => 'La fecha de fin debe ser una fecha válida.',
            'endDate.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
        ];
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['timePeriod', 'startDate', 'endDate'])) {
            $this->validateOnly($propertyName);

            if ($propertyName === 'timePeriod') {
                // Reset custom dates when switching away from custom period
                if ($this->timePeriod !== 'custom') {
                    $this->startDate = null;
                    $this->endDate = null;
                }

                // Auto-refresh data - force re-calculation by resetting computed properties
                $this->resetFinancialData();
                $this->dispatch('time-period-changed', $this->timePeriod);
                $this->dispatch('chart-data-updated');
                $this->dispatch('financial-data-refreshed');
            }

            // Auto-refresh when custom date range is complete
            if (in_array($propertyName, ['startDate', 'endDate'])) {
                if ($this->timePeriod === 'custom' && $this->startDate && $this->endDate) {
                    try {
                        // Validate the date range
                        $this->validate([
                            'startDate' => 'required|date',
                            'endDate' => 'required|date|after_or_equal:startDate'
                        ]);

                        // Auto-refresh with new date range
                        $this->resetFinancialData();
                    } catch (ValidationException $e) {
                        // Validation errors will be displayed automatically
                    }
                } elseif ($this->timePeriod === 'custom') {
                    // Still in custom mode but missing one date - no refresh yet
                } else {
                    // Not in custom mode, refresh immediately
                    $this->resetFinancialData();
                }
            }
        }

        if ($propertyName === 'perPage') {
            $this->resetPage('subscriptionsPage');
        }
    }

    /**
     * Reset financial data to force re-calculation
     */
    private function resetFinancialData(): void
    {
        // Force Livewire to re-compute computed properties by dispatching events
        $this->dispatch('financial-data-refreshed');
        $this->dispatch('chart-data-updated');
    }

    /**
     * Manual refresh method for the financial data
     */
    public function refreshData(): void
    {
        $this->resetFinancialData();
        $this->dispatch('financial-data-refreshed');
        $this->dispatch('chart-data-updated');
    }


    /**
     * Test method to ensure date filtering is working
     */
    public function testDateFilter(): array
    {
        $dateRange = $this->getDateRange();

        return [
            'period' => $this->timePeriod,
            'start_date' => $dateRange['start']->format('Y-m-d'),
            'end_date' => $dateRange['end']->format('Y-m-d'),
            'custom_start' => $this->startDate,
            'custom_end' => $this->endDate,
        ];
    }





    public function getFinancialDataProperty(): array
    {
        // Ensure user is still admin
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        return $this->calculateFinancialData();
    }

    public function getChartDataProperty(): array
    {
        // Ensure user is still admin
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        return [
            'revenue_trend' => $this->getFinancialService()->getRevenueTrendData(6)
        ];
    }

    public function getSubscriptionBreakdownProperty(): array
    {
        // Ensure user is still admin
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        return $this->getFinancialService()->getSubscriptionStatusBreakdown();
    }

    public function getSubscriptionsListProperty()
    {
        // Ensure user is still admin
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        try {
            return Subscription::with(['user:id,name,email'])
                ->orderBy('created_at', 'desc')
                ->paginate($this->perPage, ['*'], 'subscriptionsPage');
        } catch (\Exception $e) {
            Log::error('FinancialDashboard: Failed to load subscriptions list', [
                'error' => $e->getMessage(),
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $this->perPage);
        }
    }

    /**
     * Get the next billing date for a subscription from Stripe
     */
    public function getSubscriptionNextBillingDate(Subscription $subscription): ?Carbon
    {
        try {
            if ($subscription->stripe_status !== 'active') {
                return null;
            }

            // Try to get from Stripe API
            $stripeConfig = \App\Models\AdminSetting::getStripeConfig();
            \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

            $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);

            if ($stripeSubscription->current_period_end) {
                return Carbon::createFromTimestamp($stripeSubscription->current_period_end);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch next billing date from Stripe', [
                'subscription_id' => $subscription->stripe_id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function calculateFinancialData(): array
    {
        try {
            $dateRange = $this->getDateRange();

            return $this->getFinancialService()->getFinancialSummary(
                $dateRange['start'],
                $dateRange['end']
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions to be handled by Livewire
            throw $e;
        } catch (\InvalidArgumentException $e) {
            Log::warning('FinancialDashboard: Invalid arguments provided', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'time_period' => $this->timePeriod,
            ]);

            $this->hasError = true;
            $this->dispatch('show-toast', [
                'type' => 'warning',
                'message' => 'Parámetros de fecha inválidos. Por favor, verifique las fechas seleccionadas.',
            ]);

            return $this->getEmptyFinancialData();
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('FinancialDashboard: Database query failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'time_period' => $this->timePeriod,
                'sql_state' => $e->errorInfo[0] ?? null,
            ]);

            $this->hasError = true;
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Error de conexión con la base de datos. Inténtelo de nuevo en unos momentos.',
            ]);

            return $this->getEmptyFinancialData();
        } catch (\Exception $e) {
            Log::error('FinancialDashboard: Unexpected error in financial calculations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'time_period' => $this->timePeriod,
            ]);

            $this->hasError = true;
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Error inesperado al calcular los datos financieros. El equipo de soporte ha sido notificado.',
            ]);

            return $this->getEmptyFinancialData();
        }
    }

    /**
     * Return empty financial data structure for error states.
     */
    private function getEmptyFinancialData(): array
    {
        return [
            'mrr' => 0,
            'total_revenue' => 0,
            'period_revenue' => 0,
            'active_subscriptions' => 0,
            'revenue_growth' => 0,
            'arpu' => 0,
            'subscription_breakdown' => [],
            'trend_data' => [
                'labels' => [],
                'data' => []
            ],
        ];
    }

    private function getDateRange(): array
    {
        switch ($this->timePeriod) {
            case 'monthly':
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
            case 'quarterly':
                return [
                    'start' => now()->firstOfQuarter(),
                    'end' => now()->lastOfQuarter(),
                ];
            case 'yearly':
                return [
                    'start' => now()->startOfYear(),
                    'end' => now()->endOfYear(),
                ];
            case 'custom':
                return [
                    'start' => Carbon::parse($this->startDate),
                    'end' => Carbon::parse($this->endDate),
                ];
            default:
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
        }
    }




    public function render()
    {
        try {
            $financialData = $this->financialData;
            $chartData = $this->chartData;
            $subscriptionBreakdown = $this->subscriptionBreakdown;
            $subscriptionsList = $this->subscriptionsList;

            return view('livewire.admin.financial-dashboard', [
                'financialData' => $financialData,
                'chartData' => $chartData,
                'subscriptionBreakdown' => $subscriptionBreakdown,
                'subscriptionsList' => $subscriptionsList,
            ]);

        } catch (\Exception $e) {
            Log::error('FinancialDashboard: Render failed', [
                'error' => $e->getMessage(),
            ]);

            return view('livewire.admin.financial-dashboard', [
                'financialData' => [
                    'mrr' => 0,
                    'total_revenue' => 0,
                    'period_revenue' => 0,
                    'active_subscriptions' => 0,
                    'revenue_growth' => 0,
                    'arpu' => 0,
                ],
                'chartData' => ['revenue_trend' => ['labels' => [], 'data' => []]],
                'subscriptionBreakdown' => [],
                'subscriptionsList' => collect(),
            ]);
        }
    }
}
