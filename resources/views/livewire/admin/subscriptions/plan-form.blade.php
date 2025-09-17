<form wire:submit.prevent="{{ $showCreateModal ? 'createPlan' : 'updatePlan' }}">
    <div class="space-y-6">
        {{-- Basic Information --}}
        <div>
            <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Información del Plan</h4>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nombre del Plan *</label>
                    <input wire:model.live="name"
                           type="text"
                           id="name"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('name') border-red-300 @enderror">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Slug *</label>
                    <input wire:model="slug"
                           type="text"
                           id="slug"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('slug') border-red-300 @enderror">
                    @error('slug') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-4">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                <textarea wire:model="description"
                          id="description"
                          rows="3"
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('description') border-red-300 @enderror"></textarea>
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="monthlyPrice" class="block text-sm font-medium text-gray-700 mb-1">Precio Mensual (€)</label>
                    <input wire:model="monthlyPrice"
                           type="number"
                           step="0.01"
                           min="0"
                           id="monthlyPrice"
                           placeholder="0.00"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('monthlyPrice') border-red-300 @enderror">
                    @error('monthlyPrice') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="maxAlertsPerMonth" class="block text-sm font-medium text-gray-700 mb-1">Créditos por mes</label>
                    <input wire:model="maxAlertsPerMonth"
                           type="number"
                           min="0"
                           id="maxAlertsPerMonth"
                           placeholder="Ilimitado"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('maxAlertsPerMonth') border-red-300 @enderror">
                    @error('maxAlertsPerMonth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-400">Dejar vacío para ilimitado</p>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <label class="flex items-center">
                    <input wire:model="isActive"
                           type="checkbox"
                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                    <span class="ml-2 text-sm text-gray-700">Plan Activo</span>
                </label>

                <label class="flex items-center">
                    <input wire:model="isFeatured"
                           type="checkbox"
                           class="h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-600">
                    <span class="ml-2 text-sm text-gray-700">Plan Destacado <span class="text-purple-600 font-medium">(Recomendado)</span></span>
                </label>
                <p class="text-xs text-gray-500 ml-6">Este plan aparecerá con el badge "Recomendado" en toda la aplicación</p>
            </div>
        </div>

        {{-- Features Section --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Características del Plan</h4>
                <button type="button"
                        wire:click="addFeature"
                        class="inline-flex items-center rounded-md bg-blue-600 px-3 py-1 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">
                    <i class="fas fa-plus mr-1"></i>
                    Agregar
                </button>
            </div>

            <div wire:sortable="updateFeatureOrder" wire:sortable.options="{ animation: 150, handle: '.drag-handle' }" class="space-y-2">
                @foreach($features as $index => $feature)
                    <div wire:sortable.item="{{ $index }}" wire:key="feature-{{ $index }}" class="flex items-center space-x-2 bg-gray-50 rounded-md p-2">
                        <div wire:sortable.handle class="drag-handle cursor-move text-gray-400 hover:text-gray-600 px-1">
                            <i class="fas fa-grip-vertical"></i>
                        </div>
                        <input wire:model="features.{{ $index }}"
                               type="text"
                               placeholder="Característica del plan..."
                               class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <button type="button"
                                wire:click="removeFeature({{ $index }})"
                                class="inline-flex items-center rounded-md bg-red-600 px-2 py-1 text-xs font-semibold text-white shadow-sm hover:bg-red-500">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                @endforeach

                @if(empty($features))
                    <p class="text-sm text-gray-500 italic">No hay características agregadas</p>
                @endif
            </div>
        </div>
    </div>
</form>
