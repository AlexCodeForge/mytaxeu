@section('page_title', 'Mis Archivos')

<div>
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Mis Archivos CSV</h3>
                <p class="text-sm text-gray-600 mt-1">{{ $uploads->total() }} archivos subidos</p>
            </div>

            <!-- Status Filter and Auto-refresh Controls -->
            <div class="flex items-center space-x-4">
                <!-- Auto-refresh Toggle -->
                <div class="flex items-center space-x-2">
                    <label class="flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model.live="autoRefresh"
                            class="sr-only"
                        >
                        <div class="relative">
                            <div class="block bg-gray-600 w-10 h-6 rounded-full" :class="{ 'bg-primary': $wire.autoRefresh }"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition" :class="{ 'transform translate-x-4': $wire.autoRefresh }"></div>
                        </div>
                        <span class="ml-2 text-sm text-gray-700">Auto-refresh</span>
                    </label>
                </div>

                <!-- Manual Refresh Button -->
                <button
                    wire:click="refreshUploads"
                    class="flex items-center px-3 py-2 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                    title="Refrescar lista"
                >
                    <i class="fas fa-sync-alt mr-2" wire:loading.class="animate-spin" wire:target="refreshUploads"></i>
                    Refrescar
                </button>

                <!-- Status Filter -->
                <select wire:model.live="statusFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Todos los estados</option>
                    @foreach($allStatuses as $status)
                        <option value="{{ $status }}">
                            {{ \App\Models\Upload::where('status', $status)->first()?->status_label ?? ucfirst($status) }}
                            @if(isset($statusCounts[$status]))
                                ({{ $statusCounts[$status] }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Status Summary Cards -->
        @if($statusCounts)
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                @foreach(['received' => 'Recibidos', 'queued' => 'En Cola', 'processing' => 'Procesando', 'completed' => 'Completados', 'failed' => 'Fallidos'] as $status => $label)
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-{{ $status === 'completed' ? 'green' : ($status === 'failed' ? 'red' : 'blue') }}-600">
                            {{ $statusCounts[$status] ?? 0 }}
                        </div>
                        <div class="text-sm text-gray-600">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Uploads Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            <button
                                wire:click="sortBy('original_name')"
                                class="flex items-center text-xs font-medium text-gray-500 uppercase hover:text-gray-700"
                            >
                                Archivo
                                @if($sortField === 'original_name')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @else
                                    <i class="fas fa-sort ml-1 opacity-50"></i>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <button
                                wire:click="sortBy('status')"
                                class="flex items-center text-xs font-medium text-gray-500 uppercase hover:text-gray-700"
                            >
                                Estado
                                @if($sortField === 'status')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @else
                                    <i class="fas fa-sort ml-1 opacity-50"></i>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Detalles</th>
                        <th class="px-4 py-3 text-left">
                            <button
                                wire:click="sortBy('created_at')"
                                class="flex items-center text-xs font-medium text-gray-500 uppercase hover:text-gray-700"
                            >
                                Fecha
                                @if($sortField === 'created_at')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @else
                                    <i class="fas fa-sort ml-1 opacity-50"></i>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($uploads as $upload)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $upload->original_name }}</div>
                                    <div class="text-sm text-gray-500">{{ $upload->formatted_size }}</div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-{{ $upload->status_color }}-100 text-{{ $upload->status_color }}-800">
                                    {{ $upload->status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600">
                                @if($upload->rows_count)
                                    <div>{{ number_format($upload->rows_count) }} filas</div>
                                @endif
                                @if($upload->failure_reason)
                                    @if(auth()->user()->isAdmin())
                                        <div class="text-red-600 text-xs">{{ Str::limit($upload->failure_reason, 50) }}</div>
                                    @else
                                        <div class="text-red-600 text-xs">Error en el procesamiento. Contacte soporte.</div>
                                    @endif
                                @endif
                                @if($upload->processed_at)
                                    <div class="text-xs">Procesado: {{ $upload->processed_at->format('d/m/Y H:i') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-600">
                                <div>{{ $upload->created_at->format('d/m/Y H:i') }}</div>
                                <div class="text-xs text-gray-400">{{ $upload->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-4 py-4 text-sm">
                                <div class="flex items-center space-x-2">
                                    @if($upload->isProcessing())
                                        <div class="flex items-center text-blue-600">
                                            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600 mr-1"></div>
                                            <span class="text-xs">Procesando...</span>
                                        </div>
                                    @endif

                                    @if($upload->isCompleted())
                                        <button
                                            wire:click="downloadUpload({{ $upload->id }})"
                                            class="text-green-600 hover:text-green-900 text-xs"
                                            wire:loading.attr="disabled"
                                            wire:target="downloadUpload({{ $upload->id }})"
                                        >
                                            <i class="fas fa-download mr-1"></i>
                                            Descargar
                                        </button>
                                    @endif

                                    @if(auth()->user()->isAdmin())
                                        <button
                                            wire:click="deleteUpload({{ $upload->id }})"
                                            wire:confirm="¿Estás seguro de que quieres eliminar este archivo?"
                                            class="text-red-600 hover:text-red-900 text-xs"
                                            wire:loading.attr="disabled"
                                            wire:target="deleteUpload({{ $upload->id }})"
                                        >
                                            <i class="fas fa-trash mr-1"></i>
                                            Eliminar
                                        </button>
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="text-gray-500">
                                    @if($statusFilter)
                                        <i class="fas fa-filter text-2xl mb-2"></i>
                                        <p>No hay archivos con el estado "{{ $statusFilter }}"</p>
                                        <button
                                            wire:click="$set('statusFilter', '')"
                                            class="text-primary hover:text-blue-700 mt-2"
                                        >
                                            Mostrar todos los archivos
                                        </button>
                                    @else
                                        <i class="fas fa-file-csv text-2xl mb-2"></i>
                                        <p>No has subido ningún archivo CSV todavía</p>
                                        <a href="{{ route('dashboard') }}" wire:navigate class="text-primary hover:text-blue-700 mt-2">
                                            Subir mi primer archivo
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($uploads->hasPages())
            <div class="mt-6 border-t border-gray-200 pt-4">
                {{ $uploads->links('custom.pagination') }}
            </div>
        @endif

        <!-- Auto-refresh for uploads -->
        @if($autoRefresh && ($uploads->where('status', 'processing')->count() > 0 || $uploads->where('status', 'queued')->count() > 0))
            <div wire:poll.3s class="hidden"></div>
            <div class="mt-4 text-center">
                <div class="inline-flex items-center text-sm text-gray-600">
                    <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-primary mr-2"></div>
                    Actualizando automáticamente cada 3 segundos...
                </div>
            </div>
        @elseif($autoRefresh)
            <div wire:poll.10s class="hidden"></div>
            <div class="mt-4 text-center">
                <div class="inline-flex items-center text-sm text-gray-600">
                    <div class="animate-pulse rounded-full h-2 w-2 bg-green-500 mr-2"></div>
                    Auto-refresh activado (cada 10 segundos)
                </div>
            </div>
        @endif
    </div>
</div>
