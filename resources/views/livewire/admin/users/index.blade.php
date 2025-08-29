<div>
    <div class="glass-white p-6 rounded-xl shadow-lg">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Gestión de Usuarios</h3>
                <p class="text-sm text-gray-600 mt-1">{{ $users->total() }} usuarios registrados</p>
            </div>
            
            <!-- Search Input -->
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search"
                        placeholder="Buscar usuarios..."
                        class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                    >
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <div wire:loading wire:target="search" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="data-table overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            <button 
                                wire:click="sortBy('name')"
                                class="flex items-center text-xs font-medium text-gray-500 uppercase hover:text-gray-700"
                            >
                                Usuario
                                @if($sortField === 'name')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @else
                                    <i class="fas fa-sort ml-1 opacity-50"></i>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left">
                            <button 
                                wire:click="sortBy('credits')"
                                class="flex items-center text-xs font-medium text-gray-500 uppercase hover:text-gray-700"
                            >
                                Créditos
                                @if($sortField === 'credits')
                                    <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} ml-1"></i>
                                @else
                                    <i class="fas fa-sort ml-1 opacity-50"></i>
                                @endif
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                        <th class="px-4 py-3 text-left">
                            <button 
                                wire:click="sortBy('created_at')"
                                class="flex items-center text-xs font-medium text-gray-500 uppercase hover:text-gray-700"
                            >
                                Registro
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
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <img 
                                        class="h-8 w-8 rounded-full" 
                                        src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=1e40af&color=fff"
                                        alt="{{ $user->name }}"
                                    >
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold text-gray-900">{{ number_format($user->credits) }}</span>
                                <span class="text-xs text-gray-500 ml-1">créditos</span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if($user->isAdmin())
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                        <i class="fas fa-crown mr-1"></i>Admin
                                    </span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-user mr-1"></i>Usuario
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $user->created_at->format('d/m/Y') }}
                                <div class="text-xs text-gray-400">{{ $user->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                <button 
                                    wire:click="openEditCreditsModal({{ $user->id }})"
                                    class="text-primary hover:text-blue-900 mr-3 inline-flex items-center"
                                    wire:loading.attr="disabled"
                                    wire:target="openEditCreditsModal({{ $user->id }})"
                                >
                                    <div wire:loading wire:target="openEditCreditsModal({{ $user->id }})" class="animate-spin rounded-full h-3 w-3 border-b-2 border-primary mr-1"></div>
                                    <i class="fas fa-coins mr-1" wire:loading.remove wire:target="openEditCreditsModal({{ $user->id }})"></i>
                                    Créditos
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="text-gray-500">
                                    @if($search)
                                        <i class="fas fa-search text-2xl mb-2"></i>
                                        <p>No se encontraron usuarios que coincidan con "{{ $search }}"</p>
                                        <button 
                                            wire:click="$set('search', '')"
                                            class="text-primary hover:text-blue-700 mt-2"
                                        >
                                            Limpiar búsqueda
                                        </button>
                                    @else
                                        <i class="fas fa-users text-2xl mb-2"></i>
                                        <p>No hay usuarios registrados</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
            <div class="mt-6 border-t border-gray-200 pt-4">
                {{ $users->links() }}
            </div>
        @endif

        <!-- Loading Overlay -->
        <div wire:loading.flex wire:target="search,sortBy" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-xl">
            <div class="text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                <p class="text-sm text-gray-600 mt-2">Cargando...</p>
            </div>
        </div>
    </div>

    <!-- Include the Edit Credits Modal -->
    <livewire:admin.users.edit-credits-modal @credits-updated="$refresh" />
</div>
