<div class="p-6 bg-white min-h-screen">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Gestión Avanzada de Usuarios</h1>
        <p class="text-gray-600">Administra usuarios con seguimiento y análisis avanzados</p>
    </div>

    {{-- Filters and Search --}}
    <div class="bg-white p-6 rounded-lg shadow border mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            {{-- Search --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar Usuarios</label>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Nombre o email..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-900">
            </div>

            {{-- Activity Filter --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado de Actividad</label>
                <select wire:model.live="activityFilter"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-900">
                    <option value="all">Todos los Usuarios</option>
                    <option value="active">Usuarios Activos</option>
                    <option value="inactive">Usuarios Inactivos</option>
                </select>
            </div>

            {{-- Date From --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Registrado Desde</label>
                <input wire:model.live="dateFrom"
                       type="date"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-900">
            </div>

            {{-- Date To --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Registrado Hasta</label>
                <input wire:model.live="dateTo"
                       type="date"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-900">
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center space-x-4">
                <button wire:click="clearFilters"
                        class="px-3 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                    Limpiar Filtros
                </button>

            </div>

        </div>
    </div>

    {{-- Users Table --}}
    <div class="bg-white rounded-lg shadow border overflow-hidden">
        @if ($users->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                wire:click="sortBy('name')">
                                <div class="flex items-center space-x-1">
                                    <span>Usuario</span>
                                    @if ($sortBy === 'name')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Estado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Créditos
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                wire:click="sortBy('uploads_count')">
                                <div class="flex items-center space-x-1">
                                    <span>Actividad</span>
                                    @if ($sortBy === 'uploads_count')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                                wire:click="sortBy('created_at')">
                                <div class="flex items-center space-x-1">
                                    <span>Registrado</span>
                                    @if ($sortBy === 'created_at')
                                        <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-blue-500"></i>
                                    @endif
                                </div>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50 {{ $user->is_suspended ? 'bg-red-50' : '' }}">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $user->name }}
                                                @if ($user->is_admin)
                                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                        Admin
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($user->is_suspended)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-ban mr-1"></i>
                                            Suspendido
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i>
                                            Activo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ number_format($user->credits) }} créditos
                                    </div>
                                    <button wire:click="openCreditsModal({{ $user->id }})"
                                            class="text-xs text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-edit mr-1"></i>Ajustar
                                    </button>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        {{ $user->uploads_count }} subidas
                                    </div>
                                    @if ($user->uploads->count() > 0)
                                        @php
                                            $completedCount = $user->uploads->where('status', 'completed')->count();
                                            $successRate = $user->uploads_count > 0 ? round(($completedCount / $user->uploads_count) * 100) : 0;
                                        @endphp
                                        <div class="text-sm text-gray-500">
                                            {{ $successRate }}% tasa de éxito
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            Última: {{ $user->uploads->first()?->created_at?->diffForHumans() ?? 'Nunca' }}
                                        </div>
                                    @else
                                        <div class="text-sm text-gray-500">Sin actividad</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $user->created_at->format('M j, Y') }}
                                    <div class="text-xs">{{ $user->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button wire:click="openUserProfile({{ $user->id }})"
                                                class="text-blue-600 hover:text-blue-900"
                                                title="Ver Perfil">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        @if (!$user->is_admin)
                                            @if ($user->is_suspended)
                                                <button wire:click="activateUser({{ $user->id }})"
                                                        wire:confirm="¿Estás seguro de que quieres activar este usuario?"
                                                        class="text-green-600 hover:text-green-900"
                                                        title="Activar Usuario">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            @else
                                                <button wire:click="openSuspensionModal({{ $user->id }})"
                                                        class="text-red-600 hover:text-red-900"
                                                        title="Suspender Usuario">
                                                    <i class="fas fa-user-times"></i>
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $users->links('custom.pagination') }}
            </div>
        @else
            <div class="text-center py-12">
                <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No se encontraron usuarios</h3>
                <p class="text-gray-500">Intenta ajustar tus criterios de búsqueda o filtros.</p>
                @if (!empty($search) || $activityFilter !== 'all' || !empty($dateFrom) || !empty($dateTo))
                    <button wire:click="clearFilters"
                            class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Limpiar Todos los Filtros
                    </button>
                @endif
            </div>
        @endif
    </div>

    {{-- User Profile Modal --}}
    @if ($showUserModal && $selectedUser)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ activeTab: 'overview' }">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    {{-- Modal Header --}}
                    <div class="bg-white px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-12 w-12">
                                    <div class="h-12 w-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-lg">
                                        {{ strtoupper(substr($selectedUser->name, 0, 1)) }}
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900">
                                        {{ $selectedUser->name }}
                                        @if ($selectedUser->is_admin)
                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                Admin
                                            </span>
                                        @endif
                                        @if ($selectedUser->is_suspended)
                                            <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Suspendido
                                            </span>
                                        @endif
                                    </h3>
                                    <p class="text-sm text-gray-500">{{ $selectedUser->email }}</p>
                                </div>
                            </div>
                            <button wire:click="closeUserModal"
                                    class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        {{-- Tabs --}}
                        <div class="mt-4">
                            <div class="border-b border-gray-200">
                                <nav class="-mb-px flex space-x-8">
                                    <button @click="activeTab = 'overview'"
                                            :class="{ 'border-blue-500 text-blue-600': activeTab === 'overview' }"
                                            class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                        Resumen
                                    </button>
                                    <button @click="activeTab = 'activity'"
                                            :class="{ 'border-blue-500 text-blue-600': activeTab === 'activity' }"
                                            class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                        Línea de Tiempo
                                    </button>
                                </nav>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Content --}}
                    <div class="px-6 py-4 max-h-96 overflow-y-auto">
                        {{-- Overview Tab --}}
                        <div x-show="activeTab === 'overview'" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-blue-600">{{ $userStats['total_uploads'] ?? 0 }}</div>
                                    <div class="text-sm text-gray-600">Total Subidas</div>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-green-600">{{ $userStats['success_rate'] ?? 0 }}%</div>
                                    <div class="text-sm text-gray-600">Tasa de Éxito</div>
                                </div>
                                <div class="bg-purple-50 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-purple-600">{{ $userStats['total_size'] ?? '0 B' }}</div>
                                    <div class="text-sm text-gray-600">Almacenamiento Total</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="text-lg font-medium text-gray-900 mb-3">Información de la Cuenta</h4>
                                    <dl class="space-y-2">
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Fecha de Registro</dt>
                                            <dd class="text-sm text-gray-900">{{ $selectedUser->created_at->format('M j, Y') }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Días Activo</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['days_since_registration'] ?? 0 }} días</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Tasa de Compromiso</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['engagement_rate'] ?? 0 }}%</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Créditos Consumidos</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['total_credits'] ?? 0 }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div>
                                    <h4 class="text-lg font-medium text-gray-900 mb-3">Estadísticas de Procesamiento</h4>
                                    <dl class="space-y-2">
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Subidas Completadas</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['completed_uploads'] ?? 0 }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Subidas Fallidas</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['failed_uploads'] ?? 0 }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Tiempo Promedio</dt>
                                            <dd class="text-sm text-gray-900">{{ $userStats['avg_processing_time'] ?? '0s' }}</dd>
                                        </div>
                                        <div class="flex justify-between">
                                            <dt class="text-sm text-gray-500">Última Actividad</dt>
                                            <dd class="text-sm text-gray-900">
                                                {{ $userStats['last_activity'] ? \Carbon\Carbon::parse($userStats['last_activity'])->diffForHumans() : 'Nunca' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        {{-- Activity Timeline Tab --}}
                        <div x-show="activeTab === 'activity'" class="space-y-4">
                            <h4 class="text-lg font-medium text-gray-900">Actividad Reciente de Subidas</h4>
                            @if (!empty($userActivity))
                                <div class="space-y-3">
                                    @foreach ($userActivity as $activity)
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="flex-shrink-0">
                                                        @if ($activity['status'] === 'completed')
                                                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                                                <i class="fas fa-check text-green-600 text-sm"></i>
                                                            </div>
                                                        @elseif ($activity['status'] === 'failed')
                                                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                                                <i class="fas fa-times text-red-600 text-sm"></i>
                                                            </div>
                                                        @else
                                                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                                                <i class="fas fa-clock text-yellow-600 text-sm"></i>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">{{ $activity['filename'] }}</div>
                                                        <div class="text-xs text-gray-500">
                                                            {{ \Carbon\Carbon::parse($activity['created_at'])->format('M j, Y g:i A') }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm text-gray-900">{{ $activity['size'] }}</div>
                                                    @if ($activity['credits_consumed'])
                                                        <div class="text-xs text-gray-500">{{ $activity['credits_consumed'] }} créditos</div>
                                                    @endif
                                                </div>
                                            </div>
                                            @if ($activity['failure_reason'])
                                                <div class="mt-2 text-sm text-red-600">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    {{ $activity['failure_reason'] }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-8">
                                    <i class="fas fa-file-upload text-gray-400 text-3xl mb-4"></i>
                                    <p class="text-gray-500">No se encontró actividad de subidas</p>
                                </div>
                            @endif
                        </div>

                    </div>

                    {{-- Modal Footer --}}
                    <div class="bg-gray-50 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                @if (!$selectedUser->is_admin)
                                    @if ($selectedUser->is_suspended)
                                        <button wire:click="activateUser({{ $selectedUser->id }})"
                                                wire:confirm="¿Estás seguro de que quieres activar este usuario?"
                                                class="px-4 py-2 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors">
                                            <i class="fas fa-user-check mr-1"></i>
                                            Activar Usuario
                                        </button>
                                    @else
                                        <button wire:click="openSuspensionModal({{ $selectedUser->id }})"
                                                class="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition-colors">
                                            <i class="fas fa-user-times mr-1"></i>
                                            Suspender Usuario
                                        </button>
                                    @endif
                                @endif

                                <button wire:click="openCreditsModal({{ $selectedUser->id }})"
                                        class="px-4 py-2 bg-purple-600 text-white text-sm rounded hover:bg-purple-700 transition-colors">
                                    <i class="fas fa-coins mr-1"></i>
                                    Ajustar Créditos
                                </button>
                            </div>

                            <button wire:click="closeUserModal"
                                    class="px-4 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 transition-colors">
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Credits Modal --}}
    @if($showCreditsModal && $selectedUser)
        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
             wire:click="closeCreditsModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all"
                 wire:click.stop>
                {{-- Modal Header --}}
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Ajustar Créditos</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ $selectedUser->name }} ({{ $selectedUser->email }})</p>
                        <p class="text-xs text-gray-500">Créditos actuales: <span class="font-semibold">{{ number_format($selectedUser->credits) }}</span></p>
                    </div>
                    <button wire:click="closeCreditsModal"
                            class="text-gray-400 hover:text-gray-600 transition-colors"
                            type="button">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                {{-- Modal Body --}}
                <form wire:submit="updateCredits" class="p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Operación</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative">
                                <input type="radio" wire:model="creditsOperation" value="add" class="sr-only peer">
                                <div class="flex items-center justify-center p-3 border-2 border-gray-300 rounded-lg cursor-pointer transition-all peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700">
                                    <i class="fas fa-plus mr-2"></i>
                                    <span class="font-medium">Agregar</span>
                                </div>
                            </label>
                            <label class="relative">
                                <input type="radio" wire:model="creditsOperation" value="subtract" class="sr-only peer">
                                <div class="flex items-center justify-center p-3 border-2 border-gray-300 rounded-lg cursor-pointer transition-all peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700">
                                    <i class="fas fa-minus mr-2"></i>
                                    <span class="font-medium">Quitar</span>
                                </div>
                            </label>
                        </div>
                        @error('creditsOperation')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="creditsChange" class="block text-sm font-medium text-gray-700 mb-2">
                            Cantidad de Créditos
                        </label>
                        <input type="number" wire:model="creditsChange" id="creditsChange" min="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ingresa la cantidad...">
                        @error('creditsChange')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Preview --}}
                    @if($creditsChange > 0)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600 mb-1">Vista previa:</p>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Créditos actuales:</span>
                                <span class="font-semibold">{{ number_format($selectedUser->credits) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">{{ $creditsOperation === 'add' ? 'Agregar' : 'Quitar' }}:</span>
                                <span class="font-semibold {{ $creditsOperation === 'add' ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $creditsOperation === 'add' ? '+' : '-' }}{{ number_format($creditsChange) }}
                                </span>
                            </div>
                            <div class="border-t pt-2 mt-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">Nuevos créditos:</span>
                                    <span class="font-bold text-lg">
                                        {{ number_format($creditsOperation === 'add' ? $selectedUser->credits + $creditsChange : $selectedUser->credits - $creditsChange) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Footer --}}
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                        <button type="button" wire:click="closeCreditsModal"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>
                            Actualizar Créditos
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Suspension Confirmation Modal --}}
    @if($showSuspensionModal && $userToSuspend)
        @php
            $userToSuspendData = \App\Models\User::find($userToSuspend);
        @endphp
        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" wire:click="closeSuspensionModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg transform transition-all" wire:click.stop>
                {{-- Modal Header --}}
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0 h-10 w-10 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Suspender Usuario</h3>
                            <p class="text-sm text-gray-600">{{ $userToSuspendData?->name }} ({{ $userToSuspendData?->email }})</p>
                        </div>
                    </div>
                    <button wire:click="closeSuspensionModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-6">
                    <div class="mb-6">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-red-800">Confirmar Suspensión</h4>
                                    <div class="mt-2 text-sm text-red-700">
                                        <p>Estás a punto de suspender este usuario. Una vez suspendido:</p>
                                        <ul class="list-disc list-inside mt-2 space-y-1">
                                            <li>No podrá acceder al sistema</li>
                                            <li>Se bloquearán todas sus funcionalidades</li>
                                            <li>Sus subidas activas se detendrán</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="suspensionReason" class="block text-sm font-medium text-gray-700 mb-2">
                                Motivo de la Suspensión (Opcional)
                            </label>
                            <textarea
                                wire:model="suspensionReason"
                                id="suspensionReason"
                                rows="3"
                                placeholder="Motivo opcional de la suspensión..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none"
                            ></textarea>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                        <button type="button" wire:click="closeSuspensionModal"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                        <button wire:click="confirmSuspendUser"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <div wire:loading wire:target="confirmSuspendUser">
                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                Suspendiendo...
                            </div>
                            <div wire:loading.remove wire:target="confirmSuspendUser">
                                <i class="fas fa-user-times mr-2"></i>
                                Confirmar Suspensión
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
