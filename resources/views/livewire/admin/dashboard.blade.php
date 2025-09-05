<div class="p-6 bg-white min-h-screen" wire:poll.30s x-data="adminDashboard()" x-init="init()">
    {{-- Header --}}
    <div class="mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Panel de Administración</h1>
            <p class="mt-1 text-sm text-gray-600">
                Resumen del Sistema y Métricas Clave
            </p>
        </div>
    </div>

    {{-- Key Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Total Users --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-white"></i>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total de Usuarios</dt>
                        <dd class="text-2xl font-semibold text-gray-900">{{ number_format($metrics['total_users']) }}</dd>
                    </dl>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm">
                    <span class="text-green-600 font-medium">+{{ $metrics['new_users_today'] }}</span>
                    <span class="text-gray-500 ml-2">hoy</span>
                </div>
            </div>
        </div>

        {{-- Total Uploads --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-upload text-white"></i>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Total de Cargas</dt>
                        <dd class="text-2xl font-semibold text-gray-900">{{ number_format($metrics['total_uploads']) }}</dd>
                    </dl>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm">
                    <span class="text-green-600 font-medium">+{{ $metrics['uploads_today'] }}</span>
                    <span class="text-gray-500 ml-2">hoy</span>
                </div>
            </div>
        </div>

        {{-- Error Rate --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-white"></i>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Tasa de Error</dt>
                        <dd class="text-2xl font-semibold text-gray-900">{{ number_format($metrics['error_rate'], 1) }}%</dd>
                    </dl>
                </div>
            </div>
            <div class="mt-4">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-red-500 h-2 rounded-full" style="width: {{ $metrics['error_rate'] }}%"></div>
                </div>
            </div>
        </div>

        {{-- Active Users --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-clock text-white"></i>
                    </div>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dl>
                        <dt class="text-sm font-medium text-gray-500 truncate">Usuarios Activos</dt>
                        <dd class="text-2xl font-semibold text-gray-900">{{ number_format($metrics['active_users']) }}</dd>
                    </dl>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm">
                    <span class="text-purple-600 font-medium">{{ number_format($metrics['active_percentage'], 1) }}%</span>
                    <span class="text-gray-500 ml-2">del total</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Charts and Analytics --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Upload Trends Chart --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Tendencias de Carga (Últimos 7 Días)</h3>
            <div class="h-64 relative">
                            {{-- Chart Canvas --}}
            <canvas id="uploadsChart"
                    x-ref="uploadsChart"
                    class="w-full h-full border border-gray-200"
                    wire:ignore
                    style="display: block; width: 100%; height: 100%;">
            </canvas>

            {{-- Debug Info --}}
            <div class="absolute top-2 right-2 text-xs text-gray-500">
                <span x-text="chart ? 'Chart OK' : 'No Chart'"></span>
                <span> | Data: {{ count($trendData ?? []) }} points</span>
            </div>
            </div>
        </div>

        {{-- System Status --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Estado del Sistema</h3>
            <div class="space-y-4">
                {{-- Queue Status --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full {{ $systemHealth['queue_status'] === 'healthy' ? 'bg-green-500' : 'bg-red-500' }} mr-3"></div>
                        <span class="text-sm font-medium text-gray-900">Sistema de Colas</span>
                    </div>
                    <span class="text-sm text-gray-500">
                        {{ $systemHealth['queued_jobs'] }} trabajos en cola
                    </span>
                </div>

                {{-- Storage Status --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full {{ $systemHealth['storage_status'] === 'healthy' ? 'bg-green-500' : 'bg-yellow-500' }} mr-3"></div>
                        <span class="text-sm font-medium text-gray-900">Almacenamiento</span>
                    </div>
                    <span class="text-sm text-gray-500">
                        {{ $storageMetrics['total_storage_formatted'] }} utilizados
                    </span>
                </div>

                {{-- Database Status --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full bg-green-500 mr-3"></div>
                        <span class="text-sm font-medium text-gray-900">Base de Datos</span>
                    </div>
                    <span class="text-sm text-gray-500">Operacional</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Storage and Performance Metrics --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Storage Metrics --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Uso de Almacenamiento</h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Almacenamiento Total Usado</span>
                        <span>{{ $storageMetrics['total_storage_formatted'] }}</span>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Tamaño Promedio de Archivo</span>
                        <span>{{ $storageMetrics['average_file_size_formatted'] }}</span>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Archivos Procesados Hoy</span>
                        <span>{{ number_format($storageMetrics['files_today']) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Performance Metrics --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Métricas de Rendimiento</h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Tiempo Promedio de Procesamiento</span>
                        <span>{{ $performanceMetrics['average_processing_time_formatted'] }}</span>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Archivos Procesados</span>
                        <span>{{ number_format($performanceMetrics['processed_count']) }}</span>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                        <span>Estado de la Cola</span>
                        <span class="
                            @if($systemHealth['queued_jobs'] < 10) text-green-600
                            @elseif($systemHealth['queued_jobs'] < 50) text-yellow-600
                            @else text-red-600
                            @endif">
                            {{ $systemHealth['queued_jobs'] }} en cola
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Detailed Usage Analytics --}}
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Análisis Detallado de Uso</h3>
                <button wire:click="loadUsageAnalytics"
                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-1.5 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Actualizar
                </button>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Total Lines Processed --}}
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($usageAnalytics['total_lines_processed'] ?? 0) }}</p>
                            <p class="text-sm text-gray-600">Líneas Procesadas</p>
                        </div>
                    </div>
                </div>

                {{-- Successful Uploads --}}
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($usageAnalytics['successful_uploads'] ?? 0) }}</p>
                            <p class="text-sm text-gray-600">Cargas Exitosas</p>
                            <p class="text-xs text-gray-500">{{ number_format($usageAnalytics['success_rate_percentage'] ?? 0, 1) }}% éxito</p>
                        </div>
                    </div>
                </div>

                {{-- Total File Size --}}
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($usageAnalytics['total_file_size_mb'] ?? 0, 1) }}</p>
                            <p class="text-sm text-gray-600">MB Procesados</p>
                        </div>
                    </div>
                </div>

                {{-- Failed Uploads --}}
                <div class="bg-red-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($usageAnalytics['failed_uploads'] ?? 0) }}</p>
                            <p class="text-sm text-gray-600">Cargas Fallidas</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Processing Time --}}
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Tiempo Total de Procesamiento</h4>
                    <p class="text-lg font-semibold">{{ gmdate('H:i:s', $usageAnalytics['total_processing_time_seconds'] ?? 0) }}</p>
                </div>

                {{-- Average Processing Time --}}
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Tiempo Promedio por Archivo</h4>
                    <p class="text-lg font-semibold">{{ number_format($usageAnalytics['average_processing_time_seconds'] ?? 0, 1) }}s</p>
                </div>

                {{-- Successful vs Failed --}}
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Exitosos / Fallidos</h4>
                    <p class="text-lg font-semibold">
                        <span class="text-green-600">{{ number_format($usageAnalytics['successful_uploads'] ?? 0) }}</span>
                        /
                        <span class="text-red-600">{{ number_format($usageAnalytics['failed_uploads'] ?? 0) }}</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Activity and Users --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Activity --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Actividad Reciente</h3>
                <span class="text-sm text-gray-500">Últimas 24 horas</span>
            </div>
            @if($recentActivity && count($recentActivity) > 0)
                <div class="space-y-3">
                    @foreach($recentActivity as $activity)
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                @if($activity['type'] === 'upload')
                                    <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                                        <i class="fas fa-file-upload text-green-600 dark:text-green-400 text-sm"></i>
                                    </div>
                                @elseif($activity['type'] === 'user')
                                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 dark:text-blue-400 text-sm"></i>
                                    </div>
                                @else
                                    <div class="w-8 h-8 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                        <i class="fas fa-info text-gray-600 dark:text-gray-400 text-sm"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $activity['description'] }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ $activity['time'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-clock text-gray-400 text-3xl mb-4"></i>
                    <p class="text-gray-500">Sin actividad reciente</p>
                </div>
            @endif
        </div>

        {{-- Recent Users --}}
        <div class="bg-white p-6 rounded-lg shadow border">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Usuarios Recientes</h3>
                <span class="text-sm text-gray-500">Últimos 7 días</span>
            </div>
            @if($recentUsers && count($recentUsers) > 0)
                <div class="space-y-3">
                    @foreach($recentUsers as $user)
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white font-medium text-sm">
                                    {{ strtoupper(substr($user['name'], 0, 1)) }}
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $user['name'] }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ $user['email'] }} • {{ $user['uploads_count'] }} cargas
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <i class="fas fa-users text-gray-400 text-3xl mb-4"></i>
                    <p>Sin usuarios recientes</p>
                </div>
            @endif
        </div>
    </div>


</div>

{{-- Chart.js is already loaded via app.js --}}

@script
<script>
Alpine.data('adminDashboard', () => ({
    chart: null,

    init() {
        console.log('🚀 Alpine adminDashboard init started');
        console.log('🚀 Available refs:', Object.keys(this.$refs || {}));

        // Initialize chart if canvas exists
        this.$nextTick(() => {
            console.log('🔄 Next tick - checking for canvas');
            console.log('🔄 Canvas ref:', this.$refs.uploadsChart);

            if (this.$refs.uploadsChart) {
                console.log('✅ Canvas found, initializing chart');
                this.initializeChart();
            } else {
                console.error('❌ Canvas not found in refs');
            }
        });

        // Listen for Livewire updates
        this.$wire.on('dashboard-data-refreshed', () => {
            console.log('Dashboard data refreshed');
            this.updateChart();
        });

        // Listen for Livewire component updates
        Livewire.hook('morph.updated', ({ el, component }) => {
            if (el.querySelector('[x-ref="uploadsChart"]')) {
                setTimeout(() => {
                    this.initializeChart();
                }, 100);
            }
        });
    },

    initializeChart() {
        console.log('🎯 Chart initialization started');
        console.log('Alpine refs:', this.$refs);
        console.log('Window Chart:', !!window.Chart);
        console.log('Canvas element:', this.$refs.uploadsChart);

        if (this.$refs.uploadsChart) {
            const canvas = this.$refs.uploadsChart;
            console.log('📏 Canvas dimensions:', {
                width: canvas.width,
                height: canvas.height,
                clientWidth: canvas.clientWidth,
                clientHeight: canvas.clientHeight,
                offsetWidth: canvas.offsetWidth,
                offsetHeight: canvas.offsetHeight,
                style: canvas.style.cssText,
                display: getComputedStyle(canvas).display,
                visibility: getComputedStyle(canvas).visibility
            });
        }

        if (!window.Chart) {
            console.error('❌ Chart.js not loaded!');
            return;
        }

        if (!this.$refs.uploadsChart) {
            console.error('❌ Canvas not found!');
            return;
        }

        const ctx = this.$refs.uploadsChart.getContext('2d');
        const trendData = @js($trendData ?? []);

        console.log('📊 Chart data received:', trendData);
        console.log('📊 Data type:', typeof trendData);
        console.log('📊 Is array:', Array.isArray(trendData));
        console.log('📊 Data length:', trendData ? trendData.length : 0);

        // Destroy existing chart if it exists
        if (this.chart) {
            console.log('🗑️ Destroying existing chart');
            this.chart.destroy();
        }

        try {
            console.log('📈 Creating chart with trend data:', trendData);

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trendData.map(item => item.date || ''),
                    datasets: [{
                        label: 'Cargas',
                        data: trendData.map(item => item.uploads || 0),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: 'rgb(59, 130, 246)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
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
                            borderColor: 'rgb(59, 130, 246)',
                            borderWidth: 1,
                            cornerRadius: 6,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Cargas: ${context.parsed.y}`;
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
                                stepSize: 1,
                                callback: function(value) {
                                    return Math.floor(value) === value ? value : '';
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
            });

            console.log('✅ Chart created successfully!', this.chart);
            console.log('📊 Chart canvas:', this.chart.canvas);
            console.log('📊 Chart visible:', this.chart.canvas.style.display !== 'none');

            // Force a manual render to make sure chart shows up
            setTimeout(() => {
                if (this.chart) {
                    this.chart.resize();
                    this.chart.update();
                    console.log('🔄 Chart manually updated');
                }
            }, 100);

        } catch (error) {
            console.error('❌ Error creating chart:', error);
            console.error('Error details:', error.message);
            console.error('Error stack:', error.stack);

            // Try a simple fallback chart
            this.createFallbackChart(ctx);
        }
    },

    updateChart() {
        if (!this.chart) {
            this.initializeChart();
            return;
        }

        // Get fresh trend data from Livewire
        this.$wire.get('trendData').then(trendData => {
            if (!trendData || !Array.isArray(trendData)) {
                return;
            }

            this.chart.data.labels = trendData.map(item => item.date || '');
            this.chart.data.datasets[0].data = trendData.map(item => item.uploads || 0);
            this.chart.update('active');

            console.log('Chart updated with new data');
        }).catch(error => {
            console.error('Error updating chart data:', error);
        });
    },

    createFallbackChart(ctx) {
        console.log('🔄 Creating fallback chart');
        try {
            this.chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Test'],
                    datasets: [{
                        label: 'Test Data',
                        data: [10],
                        backgroundColor: 'rgba(59, 130, 246, 0.5)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Chart.js Test'
                        }
                    }
                }
            });
            console.log('✅ Fallback chart created');
        } catch (error) {
            console.error('❌ Even fallback chart failed:', error);
        }
    }
}));
</script>
@endscript
