<div class="p-6 bg-white min-h-screen">
    {{-- Header --}}
    <div class="mb-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="min-w-0 flex-1">
                <h1 class="text-3xl font-bold text-gray-900">Gestión de Códigos de Descuento</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Administra códigos de descuento para planes de suscripción e integración con Stripe
                </p>
            </div>
            <div class="mt-4 flex md:ml-4 md:mt-0">
                <button wire:click="openCreateModal"
                        class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                    <i class="fas fa-plus mr-2"></i>
                    Crear Código
                </button>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="rounded-md bg-green-50 p-4 mb-6" x-data="{ show: true }" x-show="show" x-transition>
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                    <input wire:model.live="search"
                           type="text"
                           id="search"
                           placeholder="Buscar por código o nombre..."
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
                        <option value="expired">Expirados</option>
                        <option value="valid">Válidos</option>
                    </select>
                </div>
                <div>
                    <label for="typeFilter" class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                    <select wire:model.live="typeFilter"
                            id="typeFilter"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="all">Todos</option>
                        <option value="percentage">Porcentaje</option>
                        <option value="fixed">Monto Fijo</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- Discount Codes Table --}}
    <div class="bg-white shadow border rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descuento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usos</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Planes</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($codes as $code)
                        <tr wire:key="code-{{ $code->id }}" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $code->code }}</div>
                                    <div class="text-sm text-gray-500">{{ $code->name }}</div>
                                    @if($code->description)
                                        <div class="text-xs text-gray-400 mt-1">{{ Str::limit($code->description, 50) }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <span class="font-medium">{{ $code->getFormattedValue() }}</span>
                                    <span class="text-xs text-gray-500 ml-1">
                                        ({{ $code->type === 'percentage' ? 'Porcentaje' : 'Fijo' }})
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <div class="flex items-center">
                                        <span class="font-medium">{{ $code->used_count }}</span>
                                        @if($code->max_uses)
                                            <span class="text-gray-500 mx-1">/</span>
                                            <span class="text-gray-500">{{ $code->max_uses }}</span>
                                        @else
                                            <span class="text-gray-500 ml-1">(ilimitado)</span>
                                        @endif
                                    </div>
                                    @if($code->max_uses && $code->used_count >= $code->max_uses)
                                        <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-700/10">
                                            Agotado
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    @if($code->is_global)
                                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-700/10">
                                            Todos los planes
                                        </span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($code->subscriptionPlans->take(2) as $plan)
                                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                    {{ $plan->name }}
                                                </span>
                                            @endforeach
                                            @if($code->subscriptionPlans->count() > 2)
                                                <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-700/10">
                                                    +{{ $code->subscriptionPlans->count() - 2 }} más
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex flex-col space-y-1">
                                    @if($code->is_active)
                                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-700/10">
                                            Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-700/10">
                                            Inactivo
                                        </span>
                                    @endif

                                    @if($code->expires_at)
                                        @if($code->isExpired())
                                            <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-700/10">
                                                Expirado
                                            </span>
                                        @else
                                            <div class="text-xs text-gray-500">
                                                Expira: {{ $code->expires_at->format('d/m/Y') }}
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <button wire:click="openEditModal({{ $code->id }})"
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <button wire:click="toggleStatus({{ $code->id }})"
                                            class="text-yellow-600 hover:text-yellow-900"
                                            title="{{ $code->is_active ? 'Desactivar' : 'Activar' }}">
                                        <i class="fas fa-{{ $code->is_active ? 'toggle-on' : 'toggle-off' }}"></i>
                                    </button>

                                    <button wire:click="deleteCode({{ $code->id }})"
                                            wire:confirm="¿Estás seguro de que quieres eliminar este código de descuento?"
                                            class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-ticket-alt text-4xl mb-4 text-gray-300"></i>
                                    <p>No hay códigos de descuento registrados</p>
                                    <p class="mt-1">Crea tu primer código de descuento para empezar</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($codes->hasPages())
            <div class="px-6 py-3 border-t border-gray-200">
                {{ $codes->links() }}
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }" x-show="show" x-transition>
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6" x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                    <form wire:submit="save">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900">
                                {{ $editMode ? 'Editar Código de Descuento' : 'Crear Código de Descuento' }}
                            </h3>
                        </div>

                        <div class="space-y-4">
                            {{-- Code --}}
                            <div>
                                <label for="code" class="block text-sm font-medium text-gray-700">Código</label>
                                <input wire:model="code"
                                       type="text"
                                       id="code"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('code') border-red-300 @enderror"
                                       placeholder="DESCUENTO2024">
                                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Name --}}
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Nombre</label>
                                <input wire:model="name"
                                       type="text"
                                       id="name"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('name') border-red-300 @enderror"
                                       placeholder="Descuento de Año Nuevo">
                                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Description --}}
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">Descripción</label>
                                <textarea wire:model="description"
                                          id="description"
                                          rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('description') border-red-300 @enderror"
                                          placeholder="Descripción opcional del código de descuento"></textarea>
                                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Type and Value --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="type" class="block text-sm font-medium text-gray-700">Tipo</label>
                                    <select wire:model.live="type"
                                            id="type"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        <option value="percentage">Porcentaje</option>
                                        <option value="fixed">Monto Fijo</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="value" class="block text-sm font-medium text-gray-700">
                                        Valor {{ $type === 'percentage' ? '(%)' : '(€)' }}
                                    </label>
                                    <input wire:model="value"
                                           type="number"
                                           id="value"
                                           step="0.01"
                                           min="0"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('value') border-red-300 @enderror"
                                           placeholder="{{ $type === 'percentage' ? '10' : '5.00' }}">
                                    @error('value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            {{-- Max Uses --}}
                            <div>
                                <label for="maxUses" class="block text-sm font-medium text-gray-700">Máximo de Usos</label>
                                <input wire:model="maxUses"
                                       type="number"
                                       id="maxUses"
                                       min="1"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('maxUses') border-red-300 @enderror"
                                       placeholder="Dejar vacío para ilimitado">
                                @error('maxUses') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Expires At --}}
                            <div>
                                <label for="expiresAt" class="block text-sm font-medium text-gray-700">Fecha de Expiración</label>
                                <input wire:model="expiresAt"
                                       type="datetime-local"
                                       id="expiresAt"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('expiresAt') border-red-300 @enderror">
                                @error('expiresAt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Active Toggle --}}
                            <div class="flex items-center">
                                <input wire:model="isActive"
                                       type="checkbox"
                                       id="isActive"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="isActive" class="ml-2 block text-sm text-gray-900">Código activo</label>
                            </div>

                            {{-- Global Toggle --}}
                            <div class="flex items-center">
                                <input wire:model.live="isGlobal"
                                       type="checkbox"
                                       id="isGlobal"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="isGlobal" class="ml-2 block text-sm text-gray-900">Aplica a todos los planes</label>
                            </div>

                            {{-- Plan Selection --}}
                            @if(!$isGlobal)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Planes Aplicables</label>
                                    <div class="space-y-2 max-h-32 overflow-y-auto border border-gray-300 rounded-md p-3">
                                        @foreach($availablePlans as $plan)
                                            <label class="flex items-center">
                                                <input type="checkbox"
                                                       wire:model="selectedPlans"
                                                       value="{{ $plan->id }}"
                                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <span class="ml-2 text-sm text-gray-900">{{ $plan->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('selectedPlans') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button"
                                    wire:click="closeModal"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancelar
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                                <span wire:loading.remove>{{ $editMode ? 'Actualizar' : 'Crear' }}</span>
                                <span wire:loading>Guardando...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
