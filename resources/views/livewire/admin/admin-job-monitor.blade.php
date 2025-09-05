<div class="space-y-6"
     x-data="{ autoRefresh: @entangle('autoRefresh') }"
     @if($autoRefresh) wire:poll.{{ $pollingInterval }}s="refreshData" @endif>

    {{-- Header Section --}}
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Monitoreo Administrativo de Trabajos</h2>
                <p class="mt-1 text-sm text-gray-600">Vista completa de todos los trabajos de procesamiento en el sistema</p>
            </div>

            <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                {{-- Auto-refresh toggle --}}
                <label class="flex items-center">
                    <input type="checkbox"
                           wire:model.live="autoRefresh"
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-600">Auto-actualizar</span>
                </label>

            </div>
        </div>
    </div>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-200">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $stats['total_jobs'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-200">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">En Cola</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $stats['queued_jobs'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-200">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Procesando</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $stats['processing_jobs'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-200">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Completados</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $stats['completed_jobs'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-xl border border-gray-200">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Fallidos</dt>
                            <dd class="text-lg font-medium text-gray-900">{{ $stats['failed_jobs'] }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- View Tabs --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                <button wire:click="selectView('jobs')"
                        class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm
                               {{ $selectedView === 'jobs' ? '!border-blue-500 !text-blue-600' : '' }}">
                    Trabajos Activos
                </button>
                <button wire:click="selectView('failed_jobs')"
                        class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm
                               {{ $selectedView === 'failed_jobs' ? '!border-blue-500 !text-blue-600' : '' }}">
                    Trabajos Fallidos
                </button>
                <button wire:click="selectView('stats')"
                        class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm
                               {{ $selectedView === 'stats' ? '!border-blue-500 !text-blue-600' : '' }}">
                    Estadísticas
                </button>
            </nav>
        </div>

        {{-- Content Area --}}
        <div class="p-6">
            @if($selectedView === 'jobs')
                {{-- Active Jobs View --}}
                @include('livewire.admin.admin-job-monitor.jobs-view')
            @elseif($selectedView === 'failed_jobs')
                {{-- Failed Jobs View --}}
                @include('livewire.admin.admin-job-monitor.failed-jobs-view')
            @elseif($selectedView === 'stats')
                {{-- Statistics View --}}
                @include('livewire.admin.admin-job-monitor.stats-view')
            @endif
        </div>
    </div>

        {{-- Job Logs Modal --}}
    @if($showLogsModal)
        @teleport('body')
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
             wire:click="closeLogsModal">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white"
                 wire:click.stop>

                {{-- Modal Header --}}
                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Logs del Trabajo #{{ $selectedJobId }}
                    </h3>
                    <button wire:click="closeLogsModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="py-4 max-h-96 overflow-y-auto">
                    @if(!empty($selectedJobLogs))
                                                <div class="space-y-3">
                            @foreach($selectedJobLogs as $log)
                                @php
                                    $logData = is_array($log) ? (object) $log : $log;
                                @endphp
                                <div class="border-l-4 pl-4 py-2
                                           @if($logData->level === 'error') border-red-400 bg-red-50
                                           @elseif($logData->level === 'warning') border-yellow-400 bg-yellow-50
                                           @else border-blue-400 bg-blue-50 @endif">

                                    <div class="flex items-center justify-between mb-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                   @if($logData->level === 'error') bg-red-100 text-red-800
                                                   @elseif($logData->level === 'warning') bg-yellow-100 text-yellow-800
                                                   @else bg-blue-100 text-blue-800 @endif">
                                            {{ ucfirst($logData->level) }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            {{ \Carbon\Carbon::parse($logData->created_at)->format('d/m/Y H:i:s') }}
                                        </span>
                                    </div>

                                    <p class="text-sm text-gray-800 font-medium">{{ $logData->message }}</p>

                                    @if($logData->metadata)
                                        <div class="mt-2 p-2 bg-gray-100 rounded text-xs">
                                            <details>
                                                <summary class="cursor-pointer text-gray-600 hover:text-gray-800">
                                                    Ver metadatos
                                                </summary>
                                                <pre class="mt-2 text-gray-700 whitespace-pre-wrap">{{ is_string($logData->metadata) ? $logData->metadata : json_encode(json_decode($logData->metadata, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </details>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Sin logs disponibles</h3>
                            <p class="mt-1 text-sm text-gray-500">Este trabajo no tiene logs registrados.</p>
                        </div>
                    @endif
                </div>

                {{-- Modal Footer --}}
                <div class="flex justify-end pt-4 border-t border-gray-200 space-x-2">
                    <button wire:click="closeLogsModal"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
        @endteleport
    @endif

    {{-- Loading Overlay --}}
    @teleport('body')
    <div wire:loading.flex wire:target="selectView,filterByStatus,clearFilters,refreshData,showJobLogs"
         class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-40">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <svg class="animate-spin h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-gray-700">Cargando...</span>
        </div>
    </div>
    @endteleport
</div>
