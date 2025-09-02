<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Cache;
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
    public string $timePeriod = 'monthly';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public bool $loading = false;
    public bool $hasError = false;

    protected ?FinancialDataService $financialService = null;

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

                // Clear cache and auto-refresh data
                $this->clearFinancialCache();
                $this->dispatch('time-period-changed', $this->timePeriod);
                $this->dispatch('chart-data-updated');
                $this->dispatch('financial-data-refreshed');
            }

            // Auto-refresh when custom date range is complete
            if (in_array($propertyName, ['startDate', 'endDate']) && $this->timePeriod === 'custom') {
                if ($this->startDate && $this->endDate) {
                    try {
                        // Validate the date range
                        $this->validate([
                            'startDate' => 'required|date',
                            'endDate' => 'required|date|after_or_equal:startDate'
                        ]);

                        // Auto-refresh with new date range
                        $this->clearFinancialCache();
                        $this->dispatch('financial-data-refreshed');
                        $this->dispatch('chart-data-updated');
                    } catch (ValidationException $e) {
                        // Validation errors will be displayed automatically
                    }
                }
            }
        }
    }



    public function exportData(string $format = 'csv'): void
    {
        // Ensure user is still admin
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        try {
            Log::info('FinancialDashboard: Exporting data', [
                'format' => $format,
                'time_period' => $this->timePeriod,
            ]);

            $this->dispatch('financial-data-exported', $format);

        } catch (\Exception $e) {
            Log::error('FinancialDashboard: Export failed', [
                'error' => $e->getMessage(),
                'format' => $format,
            ]);

            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'Error al exportar los datos financieros.',
            ]);
        }
    }

    public function getFinancialDataProperty(): array
    {
        // Ensure user is still admin
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        $cacheKey = "financial_dashboard_{$this->timePeriod}";

        if ($this->timePeriod === 'custom' && $this->startDate && $this->endDate) {
            $cacheKey .= "_{$this->startDate}_{$this->endDate}";
        }

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            return $this->calculateFinancialData();
        });
    }

    public function getChartDataProperty(): array
    {
        // Ensure user is still admin
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        $cacheKey = "financial_chart_data_{$this->timePeriod}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () {
            return [
                'revenue_trend' => $this->getFinancialService()->getRevenueTrendData(6)
            ];
        });
    }

    public function getSubscriptionBreakdownProperty(): array
    {
        // Ensure user is still admin
        if (!Auth::user() || !Auth::user()->isAdmin()) {
            abort(403, 'Access denied');
        }

        return $this->getFinancialService()->getSubscriptionStatusBreakdown();
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

    private function clearFinancialCache(): void
    {
        $this->getFinancialService()->clearCache();

        // Clear component-specific caches
        $patterns = [
            'financial_dashboard_*',
            'financial_chart_data_*',
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }



    public function render()
    {
        try {
            $financialData = $this->financialData;
            $chartData = $this->chartData;
            $subscriptionBreakdown = $this->subscriptionBreakdown;

            return view('livewire.admin.financial-dashboard', [
                'financialData' => $financialData,
                'chartData' => $chartData,
                'subscriptionBreakdown' => $subscriptionBreakdown,
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
            ]);
        }
    }
}
