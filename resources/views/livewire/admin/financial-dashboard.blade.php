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
      <div class="flex items-center space-x-4">

        <div class="flex items-center text-sm text-gray-500">
          <svg wire:loading class="animate-spin mr-2 h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>

        </div>
      </div>
    </div>
  </div>

  <!-- Time Period Selection -->
  <div class="rounded-lg bg-white p-6 shadow">
    <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Período de Análisis</h3>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Período de Tiempo</label>
        <select x-model="timePeriod" @change="onTimePeriodChange()" class="mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm">
          <option value="monthly">Este Mes</option>
          <option value="3months">Últimos 3 Meses</option>
          <option value="yearly">Este Año</option>
        </select>
      </div>

      <div class="sm:col-span-2 lg:col-span-1 flex items-end">
        <div class="text-sm text-gray-500">
          <span x-show="timePeriod === 'monthly'">Este mes</span>
          <span x-show="timePeriod === '3months'">Últimos 3 meses</span>
          <span x-show="timePeriod === 'yearly'">Este año</span>
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
          €{{ number_format($financialData['mrr'] ?? 0, 2) }}
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
          €{{ number_format($financialData['total_revenue'] ?? 0, 2) }}
        </dd>
        <div class="mt-2 text-sm text-gray-500">Todos los tiempos</div>
      </div>

      <!-- Period Revenue Card -->
      <div class="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
        <dt class="truncate text-sm font-medium text-gray-500">Ingresos del Período</dt>
        <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
          €{{ number_format($financialData['period_revenue'] ?? 0, 2) }}
        </dd>
        <div class="mt-2 text-sm text-gray-500">
          @if($timePeriod === 'monthly')
            Este mes
          @elseif($timePeriod === '3months')
            Últimos 3 meses
          @elseif($timePeriod === 'yearly')
            Este año
          @else
            Período actual
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
          €{{ number_format($financialData['arpu'] ?? 0, 2) }}
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
        <div class="text-sm text-gray-500">
          <span x-show="timePeriod === 'monthly'">Este mes</span>
          <span x-show="timePeriod === '3months'">Últimos 3 meses</span>
          <span x-show="timePeriod === 'yearly'">Este año</span>
        </div>
      </div>

      <div class="relative h-64">
        @php
          $hasChartData = count($chartData['revenue_trend']['data'] ?? []) > 0;
          $dataSum = array_sum($chartData['revenue_trend']['data'] ?? []);
        @endphp
        @if($hasChartData && $dataSum > 0)
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

  <!-- Subscriptions Table with Alpine.js Ultra-Fast Pagination -->
  @if(!$loading && !$hasError)
    <div class="rounded-lg bg-white shadow overflow-hidden"
         x-data="subscriptionsPagination()"
         x-init="initData(@js($this->allSubscriptionsForAlpine))">

      <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-medium leading-6 text-gray-900">Suscripciones</h3>
          <div class="flex items-center space-x-4">
            <div class="text-sm text-gray-500">
              <span x-text="totalItems"></span> suscripciones total
            </div>
            <div class="flex items-center space-x-2">
              <label class="text-sm text-gray-500">Por página:</label>
              <select x-model="perPage" @change="changePerPage()"
                      class="rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="25">25</option>
                <option value="50">50</option>
              </select>
            </div>
            <button @click="$wire.forceRefreshSubscriptions()"
                    class="text-indigo-600 hover:text-indigo-900 text-sm">
              Actualizar
            </button>
          </div>
        </div>
      </div>

      <div x-show="currentPageData.length > 0">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stripe ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha de Inicio</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Próxima Facturación</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <template x-for="subscription in currentPageData" :key="subscription.id">
                <tr class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-8 w-8">
                        <div class="h-8 w-8 rounded-full bg-indigo-500 flex items-center justify-center text-white text-sm font-medium"
                             x-text="subscription.user_name.charAt(0).toUpperCase()">
                        </div>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900" x-text="subscription.user_name"></div>
                        <div class="text-sm text-gray-500" x-text="subscription.user_email"></div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="subscription.status_color"
                          x-text="subscription.status_text">
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                    <span x-text="subscription.stripe_id.substring(0, 20) + (subscription.stripe_id.length > 20 ? '...' : '')"></span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="subscription.created_at"></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span x-text="subscription.next_billing || 'Sin fecha'"
                          :class="subscription.next_billing ? '' : 'text-gray-500'"></span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a :href="'https://dashboard.stripe.com/subscriptions/' + subscription.stripe_id"
                       target="_blank"
                       class="text-indigo-600 hover:text-indigo-900 text-xs">
                      Ver en Stripe
                    </a>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Ultra-Fast Alpine.js Pagination -->
        <div x-show="totalPages > 1" class="px-6 py-4 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <!-- Page Info -->
            <div class="text-sm text-gray-700">
              Mostrando <span x-text="startItem"></span> a <span x-text="endItem"></span> de <span x-text="totalItems"></span> resultados
            </div>

            <!-- Pagination Controls -->
            <div class="flex items-center space-x-2">
              <!-- Previous Button -->
              <button @click="previousPage()"
                      :disabled="currentPage === 1"
                      :class="currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                      class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                Anterior
              </button>

              <!-- Page Numbers -->
              <template x-for="page in visiblePages" :key="page">
                <button @click="goToPage(page)"
                        :class="page === currentPage ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        x-text="page">
                </button>
              </template>

              <!-- Next Button -->
              <button @click="nextPage()"
                      :disabled="currentPage === totalPages"
                      :class="currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'"
                      class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                Siguiente
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div x-show="currentPageData.length === 0" class="px-6 py-12 text-center">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <h4 class="mt-2 text-sm font-medium text-gray-900">No hay suscripciones</h4>
        <p class="mt-1 text-sm text-gray-500">Aún no se han creado suscripciones en el sistema.</p>
      </div>
    </div>
  @endif

</div>

<!-- Alpine.js for enhanced interactivity -->

@script
<script>
  // Ultra-Fast Alpine.js Pagination Component
  Alpine.data('subscriptionsPagination', () => ({
    // Data state
    allData: [],
    currentPageData: [],

    // Pagination state
    currentPage: 1,
    perPage: 10,
    totalItems: 0,
    totalPages: 0,

    // Computed properties for pagination info
    get startItem() {
      return ((this.currentPage - 1) * this.perPage) + 1;
    },

    get endItem() {
      const end = this.currentPage * this.perPage;
      return end > this.totalItems ? this.totalItems : end;
    },

    get visiblePages() {
      const pages = [];
      const maxVisible = 5;
      const halfVisible = Math.floor(maxVisible / 2);

      let start = Math.max(1, this.currentPage - halfVisible);
      let end = Math.min(this.totalPages, start + maxVisible - 1);

      if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
      }

      for (let i = start; i <= end; i++) {
        pages.push(i);
      }

      return pages;
    },

    // Initialize with data from Livewire
    initData(data) {
      this.allData = data || [];
      this.totalItems = this.allData.length;
      this.calculateTotalPages();
      this.updateCurrentPageData();

      console.log('Alpine pagination initialized:', {
        totalItems: this.totalItems,
        totalPages: this.totalPages,
        perPage: this.perPage
      });
    },

    // Calculate total pages
    calculateTotalPages() {
      this.totalPages = Math.ceil(this.totalItems / this.perPage);
    },

    // Update current page data based on pagination
    updateCurrentPageData() {
      const start = (this.currentPage - 1) * this.perPage;
      const end = start + this.perPage;
      this.currentPageData = this.allData.slice(start, end);

      console.log('Page updated:', {
        page: this.currentPage,
        showing: this.currentPageData.length,
        start: start + 1,
        end: Math.min(end, this.totalItems)
      });
    },

    // Navigation methods
    nextPage() {
      if (this.currentPage < this.totalPages) {
        this.currentPage++;
        this.updateCurrentPageData();
      }
    },

    previousPage() {
      if (this.currentPage > 1) {
        this.currentPage--;
        this.updateCurrentPageData();
      }
    },

    goToPage(page) {
      if (page >= 1 && page <= this.totalPages) {
        this.currentPage = page;
        this.updateCurrentPageData();
      }
    },

    // Change items per page
    changePerPage() {
      this.currentPage = 1; // Reset to first page
      this.calculateTotalPages();
      this.updateCurrentPageData();

      console.log('Per page changed to:', this.perPage);
    },

    // Refresh data from Livewire
    refreshData() {
      this.$wire.forceRefreshSubscriptions().then(() => {
        // Data will be refreshed via Livewire events
        console.log('Data refresh requested');
      });
    }
  }));

  Alpine.data('financialDashboard', () => ({
    revenueChartId: 'financial-revenue-chart',
    subscriptionChartId: 'financial-subscription-chart',

    // Filtering state
    timePeriod: @js($timePeriod ?? 'monthly'),
    lastUpdate: new Date().toLocaleTimeString(),


    // Debounce timers
    filterDebounce: null,

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
        this.updateLastUpdate();
        this.updateChart();
        this.updateSubscriptionChart();
      });


      // Listen for subscriptions refresh to update Alpine pagination
      this.$wire.on('subscriptions-refreshed', () => {
        console.log('Subscriptions refreshed, updating Alpine pagination');
        // The Alpine component will automatically get fresh data from Livewire
        // when the page re-renders with updated allSubscriptionsForAlpine
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
      const chartData = @js($chartData ?? ['revenue_trend' => ['labels' => [], 'data' => []]]);
      const trendData = chartData.revenue_trend || chartData;


      const chartConfig = {
        type: 'line',
        data: {
          labels: trendData.labels || [],
          datasets: [{
            label: 'Ingresos (€)',
            data: trendData.data || [],
            borderColor: 'rgb(99, 102, 241)',
            backgroundColor: 'rgba(99, 102, 241, 0.05)',
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
                  return `Ingresos: €${context.parsed.y.toLocaleString('es-ES', {minimumFractionDigits: 2})}`;
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
                  return '€' + value.toLocaleString('es-ES');
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
      // Wait for next tick to ensure Livewire is ready
      this.$nextTick(() => {
        const chart = window.chartManager.getChart(this.revenueChartId);
        if (!chart) {
          this.initializeChart();
          return;
        }

        // Use a more reliable method to get chart data
        try {
          this.$wire.call('getChartDataProperty').then(chartData => {
            const trendData = chartData.revenue_trend || {labels: [], data: []};

            // If no data, show empty state
            if (!trendData.labels || trendData.labels.length === 0 || trendData.data.every(val => val === 0)) {
              const newData = {
                labels: ['Sin datos'],
                datasets: [{
                  ...chart.data.datasets[0],
                  data: [0]
                }]
              };
              window.chartManager.updateChart(this.revenueChartId, newData, 'active');
              return;
            }

            const newData = {
              labels: trendData.labels,
              datasets: [{
                ...chart.data.datasets[0],
                data: trendData.data
              }]
            };

            window.chartManager.updateChart(this.revenueChartId, newData, 'active');
            console.log('Chart updated with new data:', trendData.labels.length, 'points');
          }).catch(error => {
            console.error('Failed to get chart data:', error);
            // Fallback: re-initialize chart
            this.initializeChart();
          });
        } catch (error) {
          console.error('Error updating chart:', error);
          // Final fallback: destroy and recreate chart
          window.chartManager.destroyChart(this.revenueChartId);
          this.initializeChart();
        }
      });
    },

    updateSubscriptionChart() {
      this.$nextTick(() => {
        const chart = window.chartManager.getChart(this.subscriptionChartId);
        if (!chart) {
          this.initializeSubscriptionChart();
          return;
        }

        // Get fresh subscription data from Livewire
        try {
          this.$wire.call('getSubscriptionBreakdownProperty').then(subscriptionData => {
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
            console.log('Subscription chart updated');
          }).catch(error => {
            console.error('Failed to get subscription data:', error);
            this.initializeSubscriptionChart();
          });
        } catch (error) {
          console.error('Error updating subscription chart:', error);
          window.chartManager.destroyChart(this.subscriptionChartId);
          this.initializeSubscriptionChart();
        }
      });
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
    },

    // === FILTERING METHODS ===
    onTimePeriodChange() {
      console.log('Time period changed to:', this.timePeriod);

      // Update server - charts will be updated via Livewire event
      this.updateFiltersWithDebounce();
    },

    updateFiltersWithDebounce() {
      // Clear existing debounce timer
      if (this.filterDebounce) {
        clearTimeout(this.filterDebounce);
      }

      // Set new timer for 200ms debounce
      this.filterDebounce = setTimeout(() => {
        this.updateLastUpdate();

        // Update Livewire component
        this.$wire.set('timePeriod', this.timePeriod);

        console.log('Period updated on server:', this.timePeriod);
      }, 200);
    },


    updateLastUpdate() {
      this.lastUpdate = new Date().toLocaleTimeString();
    },

    // === REFRESH METHODS ===
    refreshData() {
      console.log('Manual refresh triggered');
      this.updateLastUpdate();

      // Trigger Livewire refresh
      this.$wire.call('refreshData').then(() => {
        // Force chart refresh after data is refreshed
        setTimeout(() => {
          this.updateChart();
          this.updateSubscriptionChart();
        }, 300);
      });

      // Show feedback
      this.showToast('info', 'Actualizando datos...');
    },


  }));
</script>
@endscript
