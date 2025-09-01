@section('page_title', 'Análisis de Uso del Sistema')

<div class="space-y-6">
    <!-- System Overview -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Estadísticas del Sistema</h2>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Users -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($systemStats['total_users'] ?? 0) }}</p>
                            <p class="text-sm text-gray-600">Total de Usuarios</p>
                        </div>
                    </div>
                </div>

                <!-- Active Users -->
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($systemStats['active_users_count'] ?? 0) }}</p>
                            <p class="text-sm text-gray-600">Usuarios Activos</p>
                        </div>
                    </div>
                </div>

                <!-- Total Uploads -->
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($systemStats['total_uploads'] ?? 0) }}</p>
                            <p class="text-sm text-gray-600">Total de Archivos</p>
                        </div>
                    </div>
                </div>

                <!-- Total Lines Processed -->
                <div class="bg-yellow-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($systemStats['total_lines_processed'] ?? 0) }}</p>
                            <p class="text-sm text-gray-600">Líneas Procesadas</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Processing Time -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Tiempo Total de Procesamiento</h4>
                    <p class="text-lg font-semibold">{{ gmdate('H:i:s', $systemStats['total_processing_time_seconds'] ?? 0) }}</p>
                </div>

                <!-- Credits Consumed -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Créditos Consumidos</h4>
                    <p class="text-lg font-semibold">{{ number_format($systemStats['total_credits_consumed'] ?? 0) }}</p>
                </div>

                <!-- Average File Size -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Tamaño Promedio de Archivo</h4>
                    <p class="text-lg font-semibold">{{ round(($systemStats['average_file_size_mb'] ?? 0), 1) }} MB</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Acciones Administrativas</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                    </svg>
                    Gestionar Usuarios
                </a>

                <button wire:click="exportData"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Exportar Datos
                </button>

                <button wire:click="loadSystemStats"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Actualizar Datos
                </button>
            </div>
        </div>
    </div>

    @if(($systemStats['total_uploads'] ?? 0) === 0)
        <!-- Empty State -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Sin datos de uso</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Aún no hay datos de uso en el sistema. Los usuarios deben comenzar a subir archivos para ver estadísticas.
                </p>
            </div>
        </div>
    @endif
</div>
