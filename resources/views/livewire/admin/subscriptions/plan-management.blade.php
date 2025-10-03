<div class="p-6 bg-white min-h-screen">
    {{-- Header --}}
    <div class="mb-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="min-w-0 flex-1">
                <h1 class="text-3xl font-bold text-gray-900">Gestión de Planes de Suscripción</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Administra los planes de suscripción, precios y características
                </p>
            </div>
            <div class="mt-4 flex space-x-3 md:ml-4 md:mt-0">
                <button wire:click="syncWithStripe"
                        wire:loading.attr="disabled"
                        wire:target="syncWithStripe"
                        class="inline-flex items-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 disabled:opacity-50">
                    <i class="fas fa-sync-alt mr-2" wire:loading.class="animate-spin" wire:target="syncWithStripe"></i>
                    <span wire:loading.remove wire:target="syncWithStripe">Sincronizar con Stripe</span>
                    <span wire:loading wire:target="syncWithStripe">Sincronizando...</span>
                </button>
                <button wire:click="openCreateModal"
                        class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                    <i class="fas fa-plus mr-2"></i>
                    Crear Plan
                </button>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="rounded-md bg-green-50 p-4 mb-6" x-data="{ show: true }" x-show="show" x-transition>
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">{{ session('message') }}</p>
                </div>
                <div class="ml-auto pl-3">
                    <div class="-mx-1.5 -my-1.5">
                        <button @click="show = false" class="inline-flex rounded-md bg-green-50 p-1.5 text-green-500 hover:bg-green-100">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-md bg-red-50 p-4 mb-6" x-data="{ show: true }" x-show="show" x-transition>
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
                <div class="ml-auto pl-3">
                    <div class="-mx-1.5 -my-1.5">
                        <button @click="show = false" class="inline-flex rounded-md bg-red-50 p-1.5 text-red-500 hover:bg-red-100">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white shadow border rounded-lg mb-6">
        <div class="px-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                    <input wire:model.live="search"
                           type="text"
                           id="search"
                           placeholder="Buscar por nombre, slug o descripción..."
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                </div>
                <div>
                    <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                    <select wire:model.live="statusFilter"
                            id="statusFilter"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="all">Todos</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Plans Table --}}
    <div class="bg-white shadow border rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8"></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precios</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Características</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody wire:sortable="updatePlanOrder" wire:sortable.options="{ animation: 150, handle: '.drag-handle' }" class="bg-white divide-y divide-gray-200">
                    @forelse($plans as $plan)
                        <tr wire:sortable.item="{{ $plan->id }}" wire:key="plan-{{ $plan->id }}" class="hover:bg-gray-50">
                            <td class="px-2 py-4 whitespace-nowrap">
                                <div wire:sortable.handle class="drag-handle cursor-move text-gray-400 hover:text-gray-600 p-1">
                                    <i class="fas fa-grip-vertical"></i>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $plan->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $plan->slug }}</div>
                                        @if($plan->description)
                                            <div class="text-xs text-gray-400 mt-1">{{ Str::limit($plan->description, 50) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <div class="flex items-center mb-1">
                                        <span class="text-xs text-gray-500 w-24">Precio:</span>
                                        <span class="font-medium">{{ $plan->getFormattedPrice() }}/mes</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-xs text-gray-500 w-24">Compromiso:</span>
                                        <span class="text-xs font-medium text-blue-600">
                                            <i class="fas fa-calendar-check mr-1"></i>
                                            {{ $plan->getFormattedCommitment() }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    @if($plan->max_alerts_per_month)
                                        <div class="text-xs text-gray-500">{{ $plan->max_alerts_per_month }} créditos/mes</div>
                                    @else
                                        <div class="text-xs text-gray-500">Créditos ilimitados</div>
                                    @endif

                                    @if($plan->features && count($plan->features) > 0)
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach(array_slice($plan->features, 0, 3) as $feature)
                                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                    {{ $feature }}
                                                </span>
                                            @endforeach
                                            @if(count($plan->features) > 3)
                                                <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-700/10">
                                                    +{{ count($plan->features) - 3 }} más
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col space-y-1">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                        @if($plan->is_active)
                                            bg-green-100 text-green-800
                                        @else
                                            bg-red-100 text-red-800
                                        @endif">
                                        @if($plan->is_active)
                                            <i class="fas fa-check-circle mr-1"></i> Activo
                                        @else
                                            <i class="fas fa-times-circle mr-1"></i> Inactivo
                                        @endif
                                    </span>

                                    @if($plan->is_featured)
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800">
                                            <i class="fas fa-star mr-1"></i> Recomendado
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <button wire:click="openEditModal({{ $plan->id }})"
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button wire:click="togglePlanStatus({{ $plan->id }})"
                                            class="text-yellow-600 hover:text-yellow-900"
                                            title="{{ $plan->is_active ? 'Desactivar' : 'Activar' }}">
                                        <i class="fas fa-{{ $plan->is_active ? 'eye-slash' : 'eye' }}"></i>
                                    </button>
                                    <button wire:click="deletePlan({{ $plan->id }})"
                                            onclick="return confirm('¿Estás seguro de eliminar este plan?')"
                                            class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                                <p class="text-gray-500">No se encontraron planes de suscripción.</p>
                                <button wire:click="openCreateModal"
                                        class="mt-4 inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                                    <i class="fas fa-plus mr-2"></i>
                                    Crear primer plan
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($plans->hasPages())
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                {{ $plans->links() }}
            </div>
        @endif
    </div>

    {{-- Create Modal --}}
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showCreateModal') }" x-show="show" x-transition>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="$wire.closeModals()"></div>

                <div class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-medium text-gray-900">Crear Nuevo Plan</h3>
                        <button @click="$wire.closeModals()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    @include('livewire.admin.subscriptions.plan-form')

                    <div class="flex justify-end space-x-3 mt-6">
                        <button wire:click="closeModals"
                                class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button wire:click="createPlan"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 disabled:opacity-50">
                            <span wire:loading.remove wire:target="createPlan">Crear Plan</span>
                            <span wire:loading wire:target="createPlan">Creando...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Edit Modal --}}
    @if($showEditModal && $editingPlan)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showEditModal') }" x-show="show" x-transition>
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="$wire.closeModals()"></div>

                <div class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-medium text-gray-900">Editar Plan: {{ $editingPlan->name }}</h3>
                        <button @click="$wire.closeModals()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    @include('livewire.admin.subscriptions.plan-form')

                    <div class="flex justify-end space-x-3 mt-6">
                        <button wire:click="closeModals"
                                class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button wire:click="updatePlan"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 disabled:opacity-50">
                            <span wire:loading.remove wire:target="updatePlan">Actualizar Plan</span>
                            <span wire:loading wire:target="updatePlan">Actualizando...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
