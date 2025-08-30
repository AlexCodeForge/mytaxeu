<div class="container mx-auto px-6 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Gestión de Límites de Subida</h1>
        <p class="text-gray-600 mt-2">Administrar límites de CSV y estadísticas de usuarios</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Usuarios</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $uploadStats['total_users'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Con Límites Personalizados</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $uploadStats['users_with_custom_limits'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Subidas Hoy</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $uploadStats['total_uploads_today'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">IPs Rastreadas</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $uploadStats['total_ip_tracking_records'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                    Buscar usuarios
                </label>
                <input 
                    type="text" 
                    id="search"
                    wire:model.live.debounce.300ms="search"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                    placeholder="Nombre o email..."
                >
            </div>
            <div>
                <label for="perPage" class="block text-sm font-medium text-gray-700 mb-2">
                    Por página
                </label>
                <select 
                    id="perPage"
                    wire:model.live="perPage"
                    class="rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                >
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th wire:click="sortBy('name')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                            <div class="flex items-center space-x-1">
                                <span>Usuario</span>
                                @if($sortBy === 'name')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        @if($sortDirection === 'asc')
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                        @else
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        @endif
                                    </svg>
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Límite Actual
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Estadísticas
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Estado
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-700">
                                                {{ strtoupper(substr($user->name, 0, 2)) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $currentLimit = $user->uploadLimits->first();
                                    $limit = $currentLimit ? $currentLimit->csv_line_limit : 100;
                                @endphp
                                <div class="text-sm text-gray-900">
                                    {{ number_format($limit) }} líneas
                                </div>
                                @if($currentLimit)
                                    <div class="text-xs text-blue-600">
                                        Personalizado
                                        @if($currentLimit->expires_at)
                                            <br>Expira: {{ $currentLimit->expires_at->format('d/m/Y') }}
                                        @endif
                                    </div>
                                @else
                                    <div class="text-xs text-gray-500">Por defecto</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @php
                                    $uploadData = $user->uploads->first();
                                    $uploadCount = $uploadData->upload_count ?? 0;
                                    $totalLines = $uploadData->total_lines ?? 0;
                                @endphp
                                <div>{{ $uploadCount }} subidas</div>
                                <div>{{ number_format($totalLines) }} líneas total</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($user->is_admin)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        Admin
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Usuario
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <button 
                                        wire:click="openLimitModal({{ $user->id }})"
                                        class="text-indigo-600 hover:text-indigo-900 text-sm"
                                    >
                                        Editar Límite
                                    </button>
                                    @if($user->uploadLimits->first())
                                        <button 
                                            wire:click="resetUserLimit({{ $user->id }})"
                                            class="text-red-600 hover:text-red-900 text-sm"
                                            onclick="return confirm('¿Estás seguro de restablecer el límite a 100 líneas?')"
                                        >
                                            Restablecer
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                No se encontraron usuarios
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4">
            {{ $users->links() }}
        </div>
    </div>

    <!-- Recent Admin Actions -->
    @if(!empty($uploadStats['recent_admin_actions']))
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Acciones Recientes de Administradores</h3>
            <div class="space-y-3">
                @foreach($uploadStats['recent_admin_actions'] as $action)
                    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                        <div>
                            <span class="text-sm font-medium text-gray-900">
                                {{ $action->adminUser->name }}
                            </span>
                            <span class="text-sm text-gray-600">
                                {{ $action->action_type === 'limit_override' ? 'modificó límite de' : 'restableció límite de' }}
                            </span>
                            @if($action->targetUser)
                                <span class="text-sm font-medium text-gray-900">
                                    {{ $action->targetUser->name }}
                                </span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $action->created_at->diffForHumans() }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Limit Modal -->
    @if($showLimitModal && $selectedUser)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        Configurar Límite para {{ $selectedUser->name }}
                    </h3>
                    <button 
                        wire:click="closeLimitModal"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form wire:submit="setUserLimit" class="space-y-4">
                    <div>
                        <label for="newLimit" class="block text-sm font-medium text-gray-700 mb-1">
                            Límite de líneas CSV
                        </label>
                        <input 
                            type="number" 
                            id="newLimit"
                            wire:model="newLimit"
                            min="1"
                            max="10000"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                        >
                        @error('newLimit') 
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p> 
                        @enderror
                    </div>

                    <div>
                        <label for="expirationDate" class="block text-sm font-medium text-gray-700 mb-1">
                            Fecha de expiración (opcional)
                        </label>
                        <input 
                            type="date" 
                            id="expirationDate"
                            wire:model="expirationDate"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                        >
                        @error('expirationDate') 
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p> 
                        @enderror
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                            Notas (opcional)
                        </label>
                        <textarea 
                            id="notes"
                            wire:model="notes"
                            rows="3"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                            placeholder="Razón para el cambio de límite..."
                        ></textarea>
                        @error('notes') 
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p> 
                        @enderror
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button 
                            type="button"
                            wire:click="closeLimitModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-blue-700 rounded-md"
                        >
                            Guardar Límite
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>