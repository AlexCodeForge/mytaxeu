{{-- Detailed Statistics --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    {{-- Processing Performance --}}
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Rendimiento</h3>
        <dl class="space-y-3">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Tasa de Éxito:</dt>
                <dd class="text-sm font-semibold text-green-600">{{ $stats['success_rate'] }}%</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Tiempo Promedio:</dt>
                <dd class="text-sm font-semibold text-blue-600">{{ $stats['avg_processing_minutes'] }} min</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Total Procesados:</dt>
                <dd class="text-sm font-semibold text-gray-900">{{ $stats['total_jobs'] }}</dd>
            </div>
        </dl>
    </div>

    {{-- Status Breakdown --}}
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Estados</h3>
        <dl class="space-y-3">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Completados:</dt>
                <dd class="text-sm font-semibold text-green-600">{{ $stats['completed_jobs'] }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Procesando:</dt>
                <dd class="text-sm font-semibold text-blue-600">{{ $stats['processing_jobs'] }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">En Cola:</dt>
                <dd class="text-sm font-semibold text-yellow-600">{{ $stats['queued_jobs'] }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-500">Fallidos:</dt>
                <dd class="text-sm font-semibold text-red-600">{{ $stats['failed_jobs'] }}</dd>
            </div>
        </dl>
    </div>

    {{-- Recent Activity --}}
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Actividad Reciente</h3>
        <div class="space-y-3 max-h-64 overflow-y-auto">
            @forelse($recentActivity as $activity)
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        @php
                            $levelColor = match($activity->level) {
                                'error' => 'bg-red-100 text-red-600',
                                'warning' => 'bg-yellow-100 text-yellow-600',
                                default => 'bg-blue-100 text-blue-600'
                            };
                        @endphp
                        <div class="w-2 h-2 rounded-full {{ $levelColor }}"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-900 truncate">{{ $activity->message }}</p>
                        <div class="flex items-center space-x-2 text-xs text-gray-500">
                            <span>{{ $activity->user_name ?: 'Sistema' }}</span>
                            <span>•</span>
                            <span>{{ \Carbon\Carbon::parse($activity->created_at)->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No hay actividad reciente.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Progress Chart Placeholder --}}
<div class="mt-8 bg-white border border-gray-200 rounded-lg p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Tendencias de Procesamiento</h3>
    <div class="h-64 flex items-center justify-center bg-gray-50 rounded-lg">
        <div class="text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Gráfico de Tendencias</h3>
            <p class="mt-1 text-sm text-gray-500">Los gráficos estarán disponibles en futuras versiones.</p>
        </div>
    </div>
</div>
