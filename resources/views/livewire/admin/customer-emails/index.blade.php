<div class="p-6 bg-white min-h-screen">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Emails de Clientes</h1>
            <p class="text-gray-600 mt-1">Gestiona las comunicaciones con tus clientes</p>
        </div>
        <div class="flex space-x-2">
            <button wire:click="$refresh"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                <i class="fas fa-sync-alt mr-2"></i>Actualizar
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            {{ session('error') }}
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Email, asunto o nombre..."
                       class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            </div>

            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select wire:model.live="statusFilter" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="all">Todos</option>
                    <option value="open">Abierto</option>
                    <option value="in_progress">En Progreso</option>
                    <option value="resolved">Resuelto</option>
                    <option value="closed">Cerrado</option>
                </select>
            </div>

            <!-- Priority Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Prioridad</label>
                <select wire:model.live="priorityFilter" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <option value="all">Todas</option>
                    <option value="urgent">Urgente</option>
                    <option value="high">Alta</option>
                    <option value="normal">Normal</option>
                    <option value="low">Baja</option>
                </select>
            </div>

            <!-- Assigned Filter -->
            <div class="flex items-end">
                <label class="flex items-center">
                    <input type="checkbox" wire:model.live="showAssignedOnly"
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">Solo asignados a mí</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Conversations List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        @if($conversations->count() > 0)
            <div class="divide-y divide-gray-200">
                @foreach($conversations as $conversation)
                    <div class="p-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-3 mb-2">
                                    <!-- Status Badge -->
                                    <span class="px-2 py-1 text-xs font-medium rounded-full
                                        @if($conversation->status === 'open') bg-green-100 text-green-800
                                        @elseif($conversation->status === 'in_progress') bg-yellow-100 text-yellow-800
                                        @elseif($conversation->status === 'resolved') bg-blue-100 text-blue-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ ucfirst($conversation->status) }}
                                    </span>

                                    <!-- Priority Badge -->
                                    @if($conversation->priority !== 'normal')
                                        <span class="px-2 py-1 text-xs font-medium rounded-full
                                            @if($conversation->priority === 'urgent') bg-red-100 text-red-800
                                            @elseif($conversation->priority === 'high') bg-orange-100 text-orange-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ ucfirst($conversation->priority) }}
                                        </span>
                                    @endif

                                    <!-- Unread indicator -->
                                    @if($conversation->hasUnreadMessages())
                                        <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                                    @endif
                                </div>

                                <div class="flex items-center space-x-4 mb-1">
                                    <h3 class="text-sm font-medium text-gray-900 truncate">
                                        {{ $conversation->subject }}
                                    </h3>
                                </div>

                                <div class="flex items-center space-x-4 text-sm text-gray-500">
                                    <span>
                                        <i class="fas fa-envelope mr-1"></i>
                                        {{ $conversation->customer_email }}
                                    </span>
                                    @if($conversation->customer_name)
                                        <span>
                                            <i class="fas fa-user mr-1"></i>
                                            {{ $conversation->customer_name }}
                                        </span>
                                    @endif
                                    <span>
                                        <i class="fas fa-clock mr-1"></i>
                                        {{ $conversation->last_message_at->diffForHumans() }}
                                    </span>
                                    @if($conversation->assignedAdmin)
                                        <span>
                                            <i class="fas fa-user-tag mr-1"></i>
                                            {{ $conversation->assignedAdmin->name }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center space-x-2">
                                @if(!$conversation->assigned_to)
                                    <button wire:click="assignToMe({{ $conversation->id }})"
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Asignarme
                                    </button>
                                @endif

                                <a href="{{ route('admin.customer-emails.show', $conversation) }}"
                                   class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium transition-colors">
                                    Ver
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $conversations->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No hay conversaciones</h3>
                <p class="text-gray-500">No se encontraron emails que coincidan con tus filtros.</p>
            </div>
        @endif
    </div>
</div>

@script
<script>
    $wire.on('conversation-assigned', () => {
        $wire.$refresh();
    });

    $wire.on('status-updated', () => {
        $wire.$refresh();
    });
</script>
@endscript
