<div class="space-y-6">
    @if($this->hasData())
        <!-- Performance Metrics -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Average Processing Time -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Tiempo Promedio</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $this->getFormattedProcessingTime() }}</p>
                            <p class="text-xs text-gray-500">por archivo</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Average File Size -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Tamaño Promedio</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $this->getFormattedFileSize() }}</p>
                            <p class="text-xs text-gray-500">por archivo</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success Rate -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Tasa de Éxito</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $this->getSuccessRate() }}%</p>
                            <p class="text-xs text-gray-500">{{ $statistics['successful_uploads'] ?? 0 }} de {{ $statistics['total_uploads'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Credits -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Créditos Totales</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($statistics['total_credits_consumed'] ?? 0) }}</p>
                            <p class="text-xs text-gray-500">consumidos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Trends -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-medium text-gray-900">Tendencias de Uso</h3>
                    <div class="flex space-x-2">
                        <button wire:click="updateTrendDays(7)"
                                class="px-3 py-1 text-sm rounded-md {{ $trendDays === 7 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                            7 días
                        </button>
                        <button wire:click="updateTrendDays(14)"
                                class="px-3 py-1 text-sm rounded-md {{ $trendDays === 14 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                            14 días
                        </button>
                        <button wire:click="updateTrendDays(30)"
                                class="px-3 py-1 text-sm rounded-md {{ $trendDays === 30 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">
                            30 días
                        </button>
                    </div>
                </div>

                @if(count($trends) > 0)
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <!-- Trend Summary -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
<div>
                                    <p class="text-sm font-medium text-gray-500">Líneas en {{ $trendDays }} días</p>
                                    <p class="text-2xl font-bold text-gray-900">{{ number_format($this->getTotalTrendLines()) }}</p>
                                </div>
                                @php $trendChange = $this->getTrendChange() @endphp
                                <div class="flex items-center">
                                    @if($trendChange['direction'] === 'up')
                                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                        </svg>
                                        <span class="text-sm font-medium text-green-600 ml-1">+{{ $trendChange['value'] }}%</span>
                                    @elseif($trendChange['direction'] === 'down')
                                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                        </svg>
                                        <span class="text-sm font-medium text-red-600 ml-1">-{{ $trendChange['value'] }}%</span>
                                    @else
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
                                        </svg>
                                        <span class="text-sm font-medium text-gray-600 ml-1">Sin cambios</span>
                                    @endif
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-700">Actividad Diaria</p>
                                @foreach(array_slice($trends, 0, 5) as $trend)
                                    <div class="flex items-center justify-between py-2">
                                        <span class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($trend['date'])->format('d/m/Y') }}</span>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-medium text-gray-900">{{ number_format($trend['line_count']) }}</span>
                                            <span class="text-xs text-gray-500">líneas</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Simple Chart Visualization -->
                        <div class="space-y-4">
                            <p class="text-sm font-medium text-gray-700">Gráfico de Barras</p>
                            <div class="space-y-2">
                                @php
                                    $maxLines = max(array_column($trends, 'line_count'));
                                    $maxLines = $maxLines > 0 ? $maxLines : 1;
                                @endphp
                                @foreach(array_reverse($trends) as $trend)
                                    @php
                                        $percentage = ($trend['line_count'] / $maxLines) * 100;
                                        $date = \Carbon\Carbon::parse($trend['date']);
                                    @endphp
                                    <div class="flex items-center space-x-2">
                                        <div class="w-16 text-xs text-gray-500">{{ $date->format('m/d') }}</div>
                                        <div class="flex-1 bg-gray-200 rounded-full h-4">
                                            <div class="bg-indigo-600 h-4 rounded-full transition-all duration-300"
                                                 style="width: {{ $percentage }}%"></div>
                                        </div>
                                        <div class="w-16 text-xs text-gray-900 text-right">{{ number_format($trend['line_count']) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Sin tendencias disponibles</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            No hay datos suficientes para mostrar tendencias en los últimos {{ $trendDays }} días.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Detailed Statistics -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <!-- Upload Statistics -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Estadísticas de Subida</h3>
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Total de archivos subidos</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ number_format($statistics['total_uploads'] ?? 0) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Archivos exitosos</dt>
                            <dd class="text-sm font-medium text-green-600">{{ number_format($statistics['successful_uploads'] ?? 0) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Archivos fallidos</dt>
                            <dd class="text-sm font-medium text-red-600">{{ number_format($statistics['failed_uploads'] ?? 0) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Processing Statistics -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Estadísticas de Procesamiento</h3>
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Total líneas procesadas</dt>
                            <dd class="text-sm font-medium text-gray-900">{{ number_format($statistics['total_lines_processed'] ?? 0) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Tiempo total de procesamiento</dt>
                            <dd class="text-sm font-medium text-gray-900">
                                {{ gmdate('H:i:s', $statistics['total_processing_time_seconds'] ?? 0) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500">Total tamaño procesado</dt>
                            <dd class="text-sm font-medium text-gray-900">
                                @php
                                    $totalBytes = $statistics['total_file_size_bytes'] ?? 0;
                                    $units = ['B', 'KB', 'MB', 'GB'];
                                    $unitIndex = 0;
                                    while ($totalBytes >= 1024 && $unitIndex < count($units) - 1) {
                                        $totalBytes /= 1024;
                                        $unitIndex++;
                                    }
                                @endphp
                                {{ round($totalBytes, 1) }} {{ $units[$unitIndex] }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    @else
        <!-- Empty State -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Sin datos</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Aún no tienes estadísticas de uso. Comienza subiendo tu primer archivo CSV para ver tus métricas.
                </p>
                <div class="mt-6">
                    <a href="{{ route('uploads.create') }}" wire:navigate
                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Subir Archivo
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
