<div class="space-y-6" x-data="financialDashboard()" x-init="init()">
  <!-- Header -->
  <div class="md:flex md:items-center md:justify-between">
    <div class="min-w-0 flex-1">
      <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
        Panel Financiero
      </h2>
      <p class="mt-1 text-sm text-gray-500">
        Ingresos Recurrentes Mensuales y análisis de rendimiento financiero.
      </p>
    </div>
    <div class="mt-4 flex md:ml-4 md:mt-0">
      <div class="flex items-center text-sm text-gray-500">
        <svg wire:loading class="animate-spin mr-2 h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span wire:loading>Cargando datos...</span>
        <span wire:loading.remove class="flex items-center">
          <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
          Datos actualizados automáticamente
        </span>
      </div>
    </div>
  </div>

  <!-- Time Period Selection -->
  <div class="rounded-lg bg-white p-6 shadow">
    <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Período de Análisis</h3>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Tipo de Período</label>
        <select wire:model.live="timePeriod" class="mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm">
          <option value="monthly">Mensual</option>
          <option value="quarterly">Trimestral</option>
          <option value="yearly">Anual</option>
          <option value="custom">Rango Personalizado</option>
        </select>
      </div>

      @if($timePeriod === 'custom')
        <div>
          <label class="block text-sm font-medium text-gray-700">Fecha Inicio</label>
          <input wire:model="startDate" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
          @error('startDate') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Fecha Fin</label>
          <input wire:model="endDate" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
          @error('endDate') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>
      @endif

      <div class="sm:col-span-2 lg:col-span-1 flex items-end">
        <div class="text-sm text-gray-500">
          @if($timePeriod === 'monthly')
            <span>Este mes</span>
          @elseif($timePeriod === 'quarterly')
            <span>Este trimestre</span>
          @elseif($timePeriod === 'yearly')
            <span>Este año</span>
          @else
            <span>Rango seleccionado</span>
          @endif
        </div>
      </div>
    </div>
  </div>

  <!-- Loading State -->
  @if($loading)
    <div class="rounded-lg bg-blue-50 p-12 text-center">
      <svg class="mx-auto h-12 w-12 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
      <h3 class="mt-4 text-lg font-medium text-blue-900">Cargando datos financieros...</h3>
      <p class="mt-2 text-sm text-blue-700">Esto puede tomar unos momentos</p>
    </div>
  @endif

  <!-- Error State -->
  @if($hasError)
    <div class="rounded-lg bg-red-50 p-6">
      <div class="flex">
        <div class="flex-shrink-0">
          <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <h3 class="text-sm font-medium text-red-800">Error al cargar los datos financieros</h3>
          <div class="mt-2 text-sm text-red-700">
            <p>Hubo un problema al obtener los datos. Por favor, inténtalo de nuevo.</p>
          </div>
        </div>
      </div>
    </div>
  @endif

  <!-- KPI Cards -->
  @if(!$loading && !$hasError)
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
      <!-- MRR Card -->
      <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
        <dt class="truncate text-sm font-medium text-gray-500">Ingresos Recurrentes Mensuales</dt>
        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
          ${{ number_format($financialData['mrr'] ?? 0, 2) }}
        </dd>
        @if(isset($financialData['revenue_growth']) && $financialData['revenue_growth'] != 0)
          <div class="mt-2 flex items-center text-sm">
            @if($financialData['revenue_growth'] > 0)
              <svg class="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.414 14.586 7H12z" clip-rule="evenodd" />
              </svg>
              <span class="ml-1 text-green-600">+{{ number_format($financialData['revenue_growth'], 1) }}%</span>
            @else
              <svg class="h-4 w-4 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M12 13a1 1 0 100 2h5a1 1 0 001-1V9a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586l-4.293-4.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z" clip-rule="evenodd" />
              </svg>
              <span class="ml-1 text-red-600">{{ number_format($financialData['revenue_growth'], 1) }}%</span>
            @endif
            <span class="ml-1 text-gray-500">vs período anterior</span>
          </div>
        @endif
      </div>

      <!-- Total Revenue Card -->
      <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
        <dt class="truncate text-sm font-medium text-gray-500">Ingresos Totales</dt>
        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
          ${{ number_format($financialData['total_revenue'] ?? 0, 2) }}
        </dd>
        <div class="mt-2 text-sm text-gray-500">Todos los tiempos</div>
      </div>

      <!-- Period Revenue Card -->
      <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
        <dt class="truncate text-sm font-medium text-gray-500">Ingresos del Período</dt>
        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
          ${{ number_format($financialData['period_revenue'] ?? 0, 2) }}
        </dd>
        <div class="mt-2 text-sm text-gray-500">
          @if($timePeriod === 'monthly')
            Este mes
          @elseif($timePeriod === 'quarterly')
            Este trimestre
          @elseif($timePeriod === 'yearly')
            Este año
          @else
            Período seleccionado
          @endif
        </div>
      </div>

      <!-- Active Subscriptions Card -->
      <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
        <dt class="truncate text-sm font-medium text-gray-500">Suscripciones Activas</dt>
        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
          {{ number_format($financialData['active_subscriptions'] ?? 0) }}
        </dd>
        <div class="mt-2 text-sm text-gray-500">Usuarios activos</div>
      </div>

      <!-- ARPU Card -->
      <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
        <dt class="truncate text-sm font-medium text-gray-500">Ingreso Promedio por Usuario</dt>
        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
          ${{ number_format($financialData['arpu'] ?? 0, 2) }}
        </dd>
        <div class="mt-2 text-sm text-gray-500">ARPU mensual</div>
      </div>
    </div>
  @endif

  <!-- Charts Section -->
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <!-- Revenue Trend Chart -->
    <div class="rounded-lg bg-white p-6 shadow">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium leading-6 text-gray-900">Tendencia de Ingresos</h3>
        <div class="flex space-x-2">
          <button @click="toggleChartType('line')"
                  :class="chartType === 'line' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700'"
                  class="px-3 py-1 rounded-md text-sm font-medium">
            Línea
          </button>
          <button @click="toggleChartType('bar')"
                  :class="chartType === 'bar' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700'"
                  class="px-3 py-1 rounded-md text-sm font-medium">
            Barras
          </button>
        </div>
      </div>

      <div class="relative h-64">
        @if(count($chartData['revenue_trend']['data'] ?? []) > 0)
          <canvas id="revenueChart"
                  x-ref="revenueChart"
                  class="w-full h-full"
                  wire:ignore></canvas>
        @else
          <div class="flex items-center justify-center h-full bg-gray-50 rounded-lg">
            <div class="text-center">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              <h4 class="mt-2 text-sm font-medium text-gray-900">No hay datos financieros disponibles</h4>
              <p class="text-sm text-gray-500">Los gráficos se mostrarán cuando haya datos de ingresos.</p>
            </div>
          </div>
        @endif
      </div>
    </div>

    <!-- Subscription Status Breakdown -->
    <div class="rounded-lg bg-white p-6 shadow">
      <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Estado de Suscripciones</h3>
      @if(isset($subscriptionBreakdown) && !empty($subscriptionBreakdown))
        <div class="flex flex-col lg:flex-row lg:items-center lg:space-x-6">
          <!-- Chart Container -->
          <div class="flex-shrink-0 h-48 w-48 mx-auto lg:mx-0">
            <canvas id="subscriptionChart"
                    x-ref="subscriptionChart"
                    class="w-full h-full"
                    wire:ignore></canvas>
          </div>

          <!-- Legend -->
          <div class="mt-4 lg:mt-0 lg:flex-1">
            <div class="space-y-3">
              @foreach($subscriptionBreakdown as $status => $count)
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full mr-3
                      @if($status === 'active') bg-green-500
                      @elseif($status === 'canceled') bg-red-500
                      @elseif($status === 'past_due') bg-yellow-500
                      @elseif($status === 'incomplete') bg-orange-500
                      @elseif($status === 'trialing') bg-blue-500
                      @else bg-gray-500
                      @endif">
                    </div>
                    <span class="text-sm font-medium text-gray-900 capitalize">
                      @if($status === 'active') Activas
                      @elseif($status === 'canceled') Canceladas
                      @elseif($status === 'past_due') Vencidas
                      @elseif($status === 'incomplete') Incompletas
                      @elseif($status === 'trialing') En Prueba
                      @else {{ ucfirst($status) }}
                      @endif
                    </span>
                  </div>
                  <div class="text-right">
                    <span class="text-sm font-semibold text-gray-900">{{ number_format($count) }}</span>
                    <span class="text-xs text-gray-500 ml-1">
                      @if(array_sum($subscriptionBreakdown) > 0)
                        ({{ round(($count / array_sum($subscriptionBreakdown)) * 100, 1) }}%)
                      @else
                        (0%)
                      @endif
                    </span>
                  </div>
                </div>
              @endforeach
            </div>

            <!-- Total -->
            <div class="mt-4 pt-3 border-t border-gray-200">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-900">Total Suscripciones</span>
                <span class="text-lg font-bold text-gray-900">{{ isset($subscriptionBreakdown) ? array_sum($subscriptionBreakdown) : 0 }}</span>
              </div>
            </div>
          </div>
        </div>
      @else
        <div class="text-center py-8">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
          </svg>
          <h4 class="mt-2 text-sm font-medium text-gray-900">No hay datos de suscripciones disponibles</h4>
          <p class="mt-1 text-xs text-gray-500">Los gráficos se mostrarán cuando haya suscripciones activas.</p>
        </div>
      @endif
    </div>
  </div>

</div>

<!-- Alpine.js for enhanced interactivity -->

@script
<script>
  Alpine.data('financialDashboard', () => ({
    revenueChartId: 'financial-revenue-chart',
    subscriptionChartId: 'financial-subscription-chart',
    chartType: 'line',

    init() {
      // Initialize charts if canvas exists
      this.$nextTick(() => {
        if (this.$refs.revenueChart) {
          this.initializeChart();
        }
        if (this.$refs.subscriptionChart) {
          this.initializeSubscriptionChart();
        }
      });

      // Listen for financial data updates
      this.$wire.on('financial-data-refreshed', () => {
        console.log('Financial data refreshed');
        this.updateChart();
        this.updateSubscriptionChart();
      });

      this.$wire.on('time-period-changed', (period) => {
        console.log('Time period changed to:', period);
        this.updateChart();
      });

      this.$wire.on('chart-data-updated', () => {
        console.log('Chart data updated');
        this.updateChart();
        this.updateSubscriptionChart();
      });

      this.$wire.on('show-toast', (event) => {
        this.showToast(event.type || 'info', event.message);
      });
    },

    destroy() {
      // Cleanup charts when Alpine component is destroyed
      if (window.chartManager) {
        console.log('🗑️ Cleaning up financial dashboard charts on destroy');
        window.chartManager.destroyChart(this.revenueChartId);
        window.chartManager.destroyChart(this.subscriptionChartId);
      }
    },

    initializeChart() {
      if (!window.Chart || !this.$refs.revenueChart) {
        console.warn('Chart.js not available or canvas not found');
        return;
      }

      if (!window.chartManager) {
        console.error('❌ Chart manager not loaded!');
        return;
      }

      const canvas = this.$refs.revenueChart;
      const chartData = @js($chartData['revenue_trend'] ?? ['labels' => [], 'data' => []]);

      const chartConfig = {
        type: this.chartType,
        data: {
          labels: chartData.labels || [],
          datasets: [{
            label: 'Ingresos ($)',
            data: chartData.data || [],
            borderColor: 'rgb(99, 102, 241)',
            backgroundColor: this.chartType === 'bar' ? 'rgba(99, 102, 241, 0.1)' : 'rgba(99, 102, 241, 0.05)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgb(99, 102, 241)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              position: 'bottom',
              labels: {
                usePointStyle: true,
                padding: 20,
                font: {
                  size: 12
                }
              }
            },
            tooltip: {
              mode: 'index',
              intersect: false,
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleColor: '#fff',
              bodyColor: '#fff',
              borderColor: 'rgb(99, 102, 241)',
              borderWidth: 1,
              cornerRadius: 6,
              displayColors: false,
              callbacks: {
                label: function(context) {
                  return `Ingresos: $${context.parsed.y.toLocaleString('es-ES', {minimumFractionDigits: 2})}`;
                }
              }
            }
          },
          scales: {
            x: {
              display: true,
              grid: {
                display: false
              },
              ticks: {
                color: '#6B7280',
                font: {
                  size: 11
                }
              }
            },
            y: {
              display: true,
              beginAtZero: true,
              grid: {
                color: 'rgba(0, 0, 0, 0.05)'
              },
              ticks: {
                color: '#6B7280',
                font: {
                  size: 11
                },
                callback: function(value) {
                  return '$' + value.toLocaleString('es-ES');
                }
              }
            }
          },
          interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false
          },
          elements: {
            point: {
              hoverRadius: 8
            }
          },
          animation: {
            duration: 1000,
            easing: 'easeInOutQuart'
          }
        }
      };

      // Create chart using chart manager
      window.chartManager.createChart(this.revenueChartId, canvas, chartConfig);
    },

    initializeSubscriptionChart() {
      if (!window.Chart || !this.$refs.subscriptionChart) {
        console.warn('Chart.js not available or subscription canvas not found');
        return;
      }

      if (!window.chartManager) {
        console.error('❌ Chart manager not loaded!');
        return;
      }

      const canvas = this.$refs.subscriptionChart;
      const subscriptionData = @js($subscriptionBreakdown ?? []);

      // Check if we have any data or if all values are zero
      const hasData = Object.keys(subscriptionData).length > 0;
      const totalSubscriptions = hasData ? Object.values(subscriptionData).reduce((a, b) => a + b, 0) : 0;

      if (!hasData) {
        console.log('No subscription data available');
        return;
      }

      const statusColors = {
        'active': '#10B981',      // green-500
        'canceled': '#EF4444',    // red-500
        'past_due': '#F59E0B',    // yellow-500
        'incomplete': '#F97316',  // orange-500
        'trialing': '#3B82F6',    // blue-500
        'default': '#6B7280'      // gray-500
      };

      const labels = Object.keys(subscriptionData).map(status => {
        switch(status) {
          case 'active': return 'Activas';
          case 'canceled': return 'Canceladas';
          case 'past_due': return 'Vencidas';
          case 'incomplete': return 'Incompletas';
          case 'trialing': return 'En Prueba';
          default: return status.charAt(0).toUpperCase() + status.slice(1);
        }
      });

      const data = Object.values(subscriptionData);
      const backgroundColors = Object.keys(subscriptionData).map(status =>
        statusColors[status] || statusColors.default
      );

      // Create empty doughnut plugin for when all values are zero
      const emptyDoughnutPlugin = {
        id: 'emptyDoughnut',
        afterDraw(chart, args, options) {
          const {datasets} = chart.data;
          const {color, width, radiusDecrease} = options;
          let hasData = false;

          for (let i = 0; i < datasets.length; i += 1) {
            const dataset = datasets[i];
            const total = dataset.data.reduce((a, b) => a + b, 0);
            hasData |= total > 0;
          }

          if (!hasData) {
            const {chartArea: {left, top, right, bottom}, ctx} = chart;
            const centerX = (left + right) / 2;
            const centerY = (top + bottom) / 2;
            const r = Math.min(right - left, bottom - top) / 2;

            ctx.beginPath();
            ctx.lineWidth = width || 2;
            ctx.strokeStyle = color || 'rgba(156, 163, 175, 0.5)'; // gray-400
            ctx.arc(centerX, centerY, (r - (radiusDecrease || 20)), 0, 2 * Math.PI);
            ctx.stroke();

            // Add text in the center
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = 'rgba(107, 114, 128, 0.8)'; // gray-500
            ctx.font = '14px Inter, sans-serif';
            ctx.fillText('Sin datos', centerX, centerY);
          }
        }
      };

      const subscriptionChartConfig = {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: data,
            backgroundColor: backgroundColors,
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverBorderWidth: 3,
            hoverBorderColor: '#ffffff'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false // We have our own legend
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleColor: '#fff',
              bodyColor: '#fff',
              borderColor: '#374151',
              borderWidth: 1,
              cornerRadius: 6,
              displayColors: true,
              callbacks: {
                label: function(context) {
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  if (total === 0) return `${context.label}: 0 (0%)`;
                  const percentage = Math.round((context.parsed / total) * 100);
                  return `${context.label}: ${context.parsed} (${percentage}%)`;
                }
              }
            },
            emptyDoughnut: {
              color: 'rgba(156, 163, 175, 0.5)',
              width: 3,
              radiusDecrease: 20
            }
          },
          cutout: '50%',
          animation: {
            duration: 1000,
            easing: 'easeInOutQuart'
          }
        },
        plugins: [emptyDoughnutPlugin]
      };

      // Create subscription chart using chart manager
      window.chartManager.createChart(this.subscriptionChartId, canvas, subscriptionChartConfig);
    },

    updateChart() {
      const chart = window.chartManager.getChart(this.revenueChartId);
      if (!chart) {
        this.initializeChart();
        return;
      }

      // Get fresh chart data from Livewire
      this.$wire.get('chartData').then(chartData => {
        const trendData = chartData.revenue_trend || {labels: [], data: []};

        const newData = {
          labels: trendData.labels,
          datasets: [{
            ...chart.data.datasets[0],
            data: trendData.data
          }]
        };

        window.chartManager.updateChart(this.revenueChartId, newData, 'active');
      });
    },

    updateSubscriptionChart() {
      const chart = window.chartManager.getChart(this.subscriptionChartId);
      if (!chart) {
        this.initializeSubscriptionChart();
        return;
      }

      // Get fresh subscription data from Livewire
      this.$wire.get('subscriptionBreakdown').then(subscriptionData => {
        if (!subscriptionData || Object.keys(subscriptionData).length === 0) {
          return;
        }

        const statusColors = {
          'active': '#10B981',      // green-500
          'canceled': '#EF4444',    // red-500
          'past_due': '#F59E0B',    // yellow-500
          'incomplete': '#F97316',  // orange-500
          'trialing': '#3B82F6',    // blue-500
          'default': '#6B7280'      // gray-500
        };

        const labels = Object.keys(subscriptionData).map(status => {
          switch(status) {
            case 'active': return 'Activas';
            case 'canceled': return 'Canceladas';
            case 'past_due': return 'Vencidas';
            case 'incomplete': return 'Incompletas';
            case 'trialing': return 'En Prueba';
            default: return status.charAt(0).toUpperCase() + status.slice(1);
          }
        });

        const data = Object.values(subscriptionData);
        const backgroundColors = Object.keys(subscriptionData).map(status =>
          statusColors[status] || statusColors.default
        );

        const newData = {
          labels: labels,
          datasets: [{
            ...chart.data.datasets[0],
            data: data,
            backgroundColor: backgroundColors
          }]
        };

        // Update will trigger the empty state plugin if all values are 0
        window.chartManager.updateChart(this.subscriptionChartId, newData, 'active');
      });
    },

    toggleChartType(type) {
      if (this.chartType === type) return;

      this.chartType = type;

      // Destroy and recreate chart with new type
      window.chartManager.destroyChart(this.revenueChartId);
      this.initializeChart();
    },


    showToast(type, message) {
      // Simple toast implementation
      const toast = document.createElement('div');
      toast.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white max-w-sm transition-all duration-300 transform translate-x-full`;

      switch(type) {
        case 'success':
          toast.className += ' bg-green-500';
          break;
        case 'error':
          toast.className += ' bg-red-500';
          break;
        case 'warning':
          toast.className += ' bg-yellow-500';
          break;
        default:
          toast.className += ' bg-blue-500';
      }

      toast.innerHTML = `
        <div class="flex items-center space-x-3">
          <div class="flex-1">
            <p class="text-sm font-medium">${message}</p>
          </div>
          <button onclick="this.parentElement.parentElement.remove()" class="text-white hover:text-gray-200">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
          </button>
        </div>
      `;

      document.body.appendChild(toast);

      // Animate in
      setTimeout(() => {
        toast.classList.remove('translate-x-full');
      }, 100);

      // Auto remove after 5 seconds
      setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
      }, 5000);
    }
  }));
</script>
@endscript
