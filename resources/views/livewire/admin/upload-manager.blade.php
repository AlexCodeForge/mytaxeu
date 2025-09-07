<div class="p-6 bg-white min-h-screen">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Gestión de Cargas</h1>
        <p class="mt-1 text-sm text-gray-600">
            Gestiona y monitorea todas las cargas de archivos en la plataforma
        </p>
    </div>

    {{-- Filters and Search --}}
    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            {{-- Search --}}
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">
                    Buscar
                </label>
                <input type="text"
                       id="search"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Buscar por nombre de archivo o usuario..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Status Filter --}}
            <div>
                <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-1">
                    Filtro de Estado
                </label>
                <select id="statusFilter"
                        wire:model.live="statusFilter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos los Estados</option>
                    <option value="received">Recibido</option>
                    <option value="queued">En Cola</option>
                    <option value="processing">Procesando</option>
                    <option value="completed">Completado</option>
                    <option value="failed">Fallido</option>
                </select>
            </div>

            {{-- User Filter --}}
            <div>
                <label for="userFilter" class="block text-sm font-medium text-gray-700 mb-1">
                    Filtro de Usuario
                </label>
                <select id="userFilter"
                        wire:model.live="userFilter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos los Usuarios</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Date Range and File Size Filters --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            {{-- Date From --}}
            <div>
                <label for="dateFrom" class="block text-sm font-medium text-gray-700 mb-1">
                    Desde Fecha
                </label>
                <input type="date"
                       id="dateFrom"
                       wire:model.live="dateFrom"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Date To --}}
            <div>
                <label for="dateTo" class="block text-sm font-medium text-gray-700 mb-1">
                    Hasta Fecha
                </label>
                <input type="date"
                       id="dateTo"
                       wire:model.live="dateTo"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Min File Size --}}
            <div>
                <label for="minFileSize" class="block text-sm font-medium text-gray-700 mb-1">
                    Tamaño Mínimo (MB)
                </label>
                <input type="number"
                       id="minFileSize"
                       wire:model.live.debounce.500ms="minFileSize"
                       placeholder="0"
                       min="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Max File Size --}}
            <div>
                <label for="maxFileSize" class="block text-sm font-medium text-gray-700 mb-1">
                    Tamaño Máximo (MB)
                </label>
                <input type="number"
                       id="maxFileSize"
                       wire:model.live.debounce.500ms="maxFileSize"
                       placeholder="100"
                       min="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        {{-- Clear Filters Button --}}
        <div class="flex justify-end">
            <button wire:click="clearFilters"
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                Limpiar Filtros
            </button>
        </div>
    </div>

    {{-- Status Counts --}}
    <div class="mb-6 grid grid-cols-2 md:grid-cols-6 gap-4">
        @foreach($statusCounts as $status => $count)
            <div class="bg-white p-3 rounded-lg shadow border">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($count) }}</div>
                <div class="text-sm text-gray-600">
                    @switch($status)
                        @case('all')
                            Todos
                            @break
                        @case('received')
                            Recibido
                            @break
                        @case('queued')
                            En Cola
                            @break
                        @case('processing')
                            Procesando
                            @break
                        @case('completed')
                            Completado
                            @break
                        @case('failed')
                            Fallido
                            @break
                        @default
                            {{ ucfirst($status) }}
                    @endswitch
                </div>
            </div>
        @endforeach
    </div>


    {{-- Upload Table --}}
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th wire:click="sortBy('id')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            ID
                            @if($sortField === 'id')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Usuario
                        </th>
                        <th wire:click="sortBy('original_name')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            Nombre de Archivo
                            @if($sortField === 'original_name')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th wire:click="sortBy('size_bytes')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            Tamaño
                            @if($sortField === 'size_bytes')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Estado
                        </th>
                        <th wire:click="sortBy('created_at')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            Creado
                            @if($sortField === 'created_at')
                                <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($uploads as $upload)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $upload->id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $upload->user->name }}</div>
                                <div class="text-sm text-gray-500">{{ $upload->user->email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">{{ Str::limit($upload->original_name, 30) }}</div>
                                @if($upload->rows_count)
                                    <div class="text-sm text-gray-500">{{ number_format($upload->rows_count) }} filas</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $upload->formatted_size }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    @if($upload->status === 'completed') bg-green-100 text-green-800
                                    @elseif($upload->status === 'failed') bg-red-100 text-red-800
                                    @elseif($upload->status === 'processing') bg-blue-100 text-blue-800
                                    @elseif($upload->status === 'queued') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    @switch($upload->status)
                                        @case('received')
                                            Recibido
                                            @break
                                        @case('queued')
                                            En Cola
                                            @break
                                        @case('processing')
                                            Procesando
                                            @break
                                        @case('completed')
                                            Completado
                                            @break
                                        @case('failed')
                                            Fallido
                                            @break
                                        @default
                                            {{ ucfirst($upload->status) }}
                                    @endswitch
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <div>{{ $upload->created_at->format('M j, Y') }}</div>
                                <div class="text-xs">{{ $upload->created_at->format('H:i') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <div class="text-lg mb-2">No se encontraron cargas</div>
                                <div class="text-sm">Intenta ajustar tus filtros o criterios de búsqueda</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($uploads->hasPages())
            <div class="bg-white px-6 py-3 border-t border-gray-200">
                {{ $uploads->links('custom.pagination') }}
            </div>
        @endif
    </div>

    {{-- Upload Details Modal --}}
    @if($showDetailsModal && $selectedUpload)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeDetailsModal"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <div class="bg-white px-6 pt-6 pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">
                                Detalles de la Carga
                            </h3>
                            <button wire:click="closeDetailsModal"
                                    class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">ID de Carga</label>
                                    <div class="text-sm text-gray-900">{{ $selectedUpload->id }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Estado</label>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        @if($selectedUpload->status === 'completed') bg-green-100 text-green-800
                                        @elseif($selectedUpload->status === 'failed') bg-red-100 text-red-800
                                        @elseif($selectedUpload->status === 'processing') bg-blue-100 text-blue-800
                                        @elseif($selectedUpload->status === 'queued') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        @switch($selectedUpload->status)
                                            @case('received')
                                                Recibido
                                                @break
                                            @case('queued')
                                                En Cola
                                                @break
                                            @case('processing')
                                                Procesando
                                                @break
                                            @case('completed')
                                                Completado
                                                @break
                                            @case('failed')
                                                Fallido
                                                @break
                                            @default
                                                {{ ucfirst($selectedUpload->status) }}
                                        @endswitch
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Usuario</label>
                                    <div class="text-sm text-gray-900">{{ $selectedUpload->user->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $selectedUpload->user->email }}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Tamaño de Archivo</label>
                                    <div class="text-sm text-gray-900">{{ $selectedUpload->formatted_size }}</div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nombre de Archivo</label>
                                <div class="text-sm text-gray-900 break-words">{{ $selectedUpload->original_name }}</div>
                            </div>

                            @if($selectedUpload->rows_count)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Cantidad de Filas</label>
                                    <div class="text-sm text-gray-900">{{ number_format($selectedUpload->rows_count) }}</div>
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Creado el</label>
                                    <div class="text-sm text-gray-900">{{ $selectedUpload->created_at->format('Y-m-d H:i:s') }}</div>
                                </div>
                                @if($selectedUpload->processed_at)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Procesado el</label>
                                        <div class="text-sm text-gray-900">{{ $selectedUpload->processed_at->format('Y-m-d H:i:s') }}</div>
                                    </div>
                                @endif
                            </div>

                            @if($selectedUpload->failure_reason)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Razón del Fallo</label>
                                    <div class="text-sm text-red-600 bg-red-50 p-2 rounded">
                                        {{ $selectedUpload->failure_reason }}
                                    </div>
                                </div>
                            @endif

                            {{-- Upload Metrics if available --}}
                            @if($selectedUpload->uploadMetric)
                                <div class="border-t border-gray-200 pt-4">
                                    <h4 class="text-sm font-medium text-gray-700 mb-2">Métricas de Procesamiento</h4>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        @if($selectedUpload->uploadMetric->processing_duration_seconds)
                                            <div>
                                                <span class="text-gray-600">Duración:</span>
                                                <span class="text-gray-900">{{ $selectedUpload->uploadMetric->processing_duration_seconds }}s</span>
                                            </div>
                                        @endif
                                        @if($selectedUpload->uploadMetric->credits_consumed)
                                            <div>
                                                <span class="text-gray-600">Créditos:</span>
                                                <span class="text-gray-900">{{ $selectedUpload->uploadMetric->credits_consumed }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="bg-gray-50 px-6 py-3 flex justify-end">
                        <button wire:click="closeDetailsModal"
                                class="px-4 py-2 bg-gray-300 text-gray-700 text-sm rounded hover:bg-gray-400 transition-colors">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Automatic status monitoring --}}
    <div wire:poll.5s="checkForStatusUpdates" class="hidden"></div>

    </div>

</div>

