{{-- Filters Section --}}
<div class="mb-6 space-y-4">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
        {{-- Status Filter Tabs --}}
        <div class="flex flex-wrap gap-2">
            <button wire:click="filterByStatus('')"
                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium {{ empty($statusFilter) ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' }}">
                Todos
                @if($statusCounts['all'] > 0)
                    <span class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-blue-100 bg-blue-600 rounded-full">{{ $statusCounts['all'] }}</span>
                @endif
            </button>

            <button wire:click="filterByStatus('queued')"
                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium {{ $statusFilter === 'queued' ? 'bg-yellow-100 text-yellow-700' : 'text-gray-500 hover:text-gray-700' }}">
                En Cola
                @if($statusCounts['queued'] > 0)
                    <span class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-yellow-100 bg-yellow-600 rounded-full">{{ $statusCounts['queued'] }}</span>
                @endif
            </button>

            <button wire:click="filterByStatus('processing')"
                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium {{ $statusFilter === 'processing' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700' }}">
                Procesando
                @if($statusCounts['processing'] > 0)
                    <span class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-blue-100 bg-blue-600 rounded-full">{{ $statusCounts['processing'] }}</span>
                @endif
            </button>

            <button wire:click="filterByStatus('completed')"
                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium {{ $statusFilter === 'completed' ? 'bg-green-100 text-green-700' : 'text-gray-500 hover:text-gray-700' }}">
                Completado
                @if($statusCounts['completed'] > 0)
                    <span class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-green-100 bg-green-600 rounded-full">{{ $statusCounts['completed'] }}</span>
                @endif
            </button>

            <button wire:click="filterByStatus('failed')"
                    class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium {{ $statusFilter === 'failed' ? 'bg-red-100 text-red-700' : 'text-gray-500 hover:text-gray-700' }}">
                Fallido
                @if($statusCounts['failed'] > 0)
                    <span class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full">{{ $statusCounts['failed'] }}</span>
                @endif
            </button>
        </div>

        {{-- Search and User Filter --}}
        <div class="flex space-x-3">
            {{-- User Filter --}}
            <select wire:model.live="userFilter"
                    class="block w-48 px-3 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                <option value="">Todos los usuarios</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                @endforeach
            </select>

            {{-- Search Input --}}
            <div class="flex-1 lg:max-w-xs">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input wire:model.live.debounce.300ms="search"
                           type="text"
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                           placeholder="Buscar trabajos...">
                </div>
            </div>
        </div>
    </div>

    {{-- Date Range Filter --}}
    <div class="flex space-x-3">
        <div>
            <label class="block text-sm font-medium text-gray-700">Desde:</label>
            <input wire:model.live="dateFrom"
                   type="date"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Hasta:</label>
            <input wire:model.live="dateTo"
                   type="date"
                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
        </div>

        {{-- Clear Filters --}}
        @if(!empty($statusFilter) || !empty($userFilter) || !empty($search) || !empty($dateFrom) || !empty($dateTo))
            <div class="flex items-end">
                <button wire:click="clearFilters"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Limpiar Filtros
                </button>
            </div>
        @endif
    </div>
</div>

{{-- Jobs Table --}}
@if($jobs->count() > 0)
    <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
        <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archivo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Creado</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completado</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($jobs as $job)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            #{{ $job->id }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div>
                                <div class="font-medium text-gray-900">{{ $job->user_name ?: 'N/A' }}</div>
                                <div class="text-gray-500">{{ $job->user_email ?: 'N/A' }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $job->original_name ?: "Archivo #{$job->id}" }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $color = $this->getStatusColor($job->status);
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                       @if($color === 'green') bg-green-100 text-green-800
                                       @elseif($color === 'red') bg-red-100 text-red-800
                                       @elseif($color === 'blue') bg-blue-100 text-blue-800
                                       @elseif($color === 'yellow') bg-yellow-100 text-yellow-800
                                       @else bg-gray-100 text-gray-800 @endif">
                                {{ $this->getStatusLabel($job->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ \Carbon\Carbon::parse($job->created_at)->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $job->processed_at ? \Carbon\Carbon::parse($job->processed_at)->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center space-x-2">
                                <button wire:click="showJobLogs({{ $job->id }})"
                                        class="text-blue-600 hover:text-blue-900 transition-colors duration-150">
                                    Ver Logs
                                </button>

                                @if($job->status === 'queued')
                                    <button wire:click="confirmCancel({{ $job->id }})"
                                            class="text-yellow-600 hover:text-yellow-900 transition-colors duration-150">
                                        Cancelar
                                    </button>
                                @elseif(in_array($job->status, ['completed', 'failed']))
                                    <button wire:click="confirmDelete({{ $job->id }})"
                                            class="text-red-600 hover:text-red-900 transition-colors duration-150">
                                        Eliminar
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($jobs->hasPages())
        <div class="bg-white px-6 py-3 border-t border-gray-200">
            {{ $jobs->links('custom.pagination') }}
        </div>
    @endif
@else
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No se encontraron trabajos</h3>
        <p class="mt-1 text-sm text-gray-500">
            @if(!empty($statusFilter) || !empty($userFilter) || !empty($search) || !empty($dateFrom) || !empty($dateTo))
                No hay trabajos que coincidan con los filtros actuales.
            @else
                No hay trabajos en el sistema.
            @endif
        </p>
    </div>
@endif
