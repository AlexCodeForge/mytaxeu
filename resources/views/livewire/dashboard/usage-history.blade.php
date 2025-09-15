<div class="space-y-6">
    <!-- Filters Section -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Historial de Uso</h3>
                <button wire:click="exportToCsv"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Exportar CSV
                </button>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Start Date Filter -->
                <div>
                    <label for="startDate" class="block text-sm font-medium text-gray-700">Fecha Inicio</label>
                    <input type="date"
                           id="startDate"
                           wire:model="startDate"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>

                <!-- End Date Filter -->
                <div>
                    <label for="endDate" class="block text-sm font-medium text-gray-700">Fecha Fin</label>
                    <input type="date"
                           id="endDate"
                           wire:model="endDate"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>

                <!-- Status Filter -->
<div>
                    <label for="statusFilter" class="block text-sm font-medium text-gray-700">Estado</label>
                    <select id="statusFilter"
                            wire:model="statusFilter"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Todos los estados</option>
                        <option value="completed">Completado</option>
                        <option value="failed">Fallido</option>
                        <option value="processing">Procesando</option>
                    </select>
                </div>

                <!-- Filter Actions -->
                <div class="flex items-end space-x-2">
                    <button wire:click="applyFilters"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Aplicar Filtros
                    </button>
                    <button wire:click="clearFilters"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="overflow-x-auto">
            @if($metrics->count() > 0)
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th wire:click="sortBy('file_name')"
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hover:bg-gray-100">
                                Archivo {{ $this->getSortIcon('file_name') }}
                            </th>
                            <th wire:click="sortBy('line_count')"
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hover:bg-gray-100">
                                Líneas {{ $this->getSortIcon('line_count') }}
                            </th>
                            <th wire:click="sortBy('file_size_bytes')"
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hover:bg-gray-100">
                                Tamaño {{ $this->getSortIcon('file_size_bytes') }}
                            </th>
                            <th wire:click="sortBy('processing_duration_seconds')"
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hover:bg-gray-100">
                                Tiempo {{ $this->getSortIcon('processing_duration_seconds') }}
                            </th>
                            <th wire:click="sortBy('credits_consumed')"
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hover:bg-gray-100">
                                Créditos {{ $this->getSortIcon('credits_consumed') }}
                            </th>
                            <th wire:click="sortBy('status')"
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hover:bg-gray-100">
                                Estado {{ $this->getSortIcon('status') }}
                            </th>
                            <th wire:click="sortBy('created_at')"
                                class="cursor-pointer px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hover:bg-gray-100">
                                Fecha {{ $this->getSortIcon('created_at') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($metrics as $metric)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $metric->file_name }}</div>
                                    @if($metric->error_message)
                                        <div class="text-xs text-red-600 mt-1">{{ Str::limit($metric->error_message, 50) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ number_format($metric->line_count) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $metric->formatted_size }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if($metric->processing_duration_seconds)
                                        {{ $metric->processing_duration_seconds }}s
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $metric->credits_consumed }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $this->getStatusBadgeClass($metric->status) }}">
                                        {{ $this->getStatusText($metric->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div>{{ $metric->created_at->format('d/m/Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ $metric->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    @if($metric->status === 'completed' && $metric->upload && $metric->upload->transformed_path)
                                        <a href="{{ route('download.upload', $metric->upload) }}"
                                           class="text-indigo-600 hover:text-indigo-900">
                                            Descargar
                                        </a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="bg-white px-6 py-3 border-t border-gray-200">
                    {{ $metrics->links('custom.pagination') }}
                </div>
            @else
                <!-- Empty State -->
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No hay archivos procesados</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        No se encontraron archivos en el rango de fechas seleccionado.
                    </p>
                    @if($startDate || $endDate || $statusFilter)
                        <div class="mt-6">
                            <button wire:click="clearFilters"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                Limpiar Filtros
                            </button>
                        </div>
                    @else
                        <div class="mt-6">
                            <a href="{{ route('uploads.create') }}" wire:navigate
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Subir Primer Archivo
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@script
<script>
    $wire.on('download-ready', (event) => {
        const link = document.createElement('a');
        const blob = new Blob([event.content], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', event.filename);
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);
    });
</script>
@endscript
