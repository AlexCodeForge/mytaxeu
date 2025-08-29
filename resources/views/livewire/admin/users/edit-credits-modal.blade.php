<div>
    @if($showModal)
        <!-- Modal Backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <!-- Modal Container -->
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Ajustar Créditos</h3>
                        @if($user)
                            <p class="text-sm text-gray-600 mt-1">{{ $user->name }} ({{ $user->email }})</p>
                            <p class="text-xs text-gray-500">Créditos actuales: <span class="font-semibold">{{ number_format($user->credits) }}</span></p>
                        @endif
                    </div>
                    <button 
                        wire:click="closeModal"
                        class="text-gray-400 hover:text-gray-600 transition-colors"
                        type="button"
                    >
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <form wire:submit="updateCredits" class="p-6 space-y-6">
                    <!-- Operation Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Operación</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative">
                                <input 
                                    type="radio" 
                                    wire:model="operation" 
                                    value="add"
                                    class="sr-only peer"
                                >
                                <div class="flex items-center justify-center p-3 border-2 border-gray-300 rounded-lg cursor-pointer transition-all peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-700">
                                    <i class="fas fa-plus mr-2"></i>
                                    <span class="font-medium">Agregar</span>
                                </div>
                            </label>
                            <label class="relative">
                                <input 
                                    type="radio" 
                                    wire:model="operation" 
                                    value="subtract"
                                    class="sr-only peer"
                                >
                                <div class="flex items-center justify-center p-3 border-2 border-gray-300 rounded-lg cursor-pointer transition-all peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-700">
                                    <i class="fas fa-minus mr-2"></i>
                                    <span class="font-medium">Quitar</span>
                                </div>
                            </label>
                        </div>
                        @error('operation')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Credits Amount -->
                    <div>
                        <label for="creditsChange" class="block text-sm font-medium text-gray-700 mb-2">
                            Cantidad de Créditos
                        </label>
                        <input 
                            type="number" 
                            id="creditsChange"
                            wire:model="creditsChange"
                            min="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary"
                            placeholder="Ingrese la cantidad..."
                        >
                        @error('creditsChange')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Reason -->
                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Motivo <span class="text-red-500">*</span>
                        </label>
                        <textarea 
                            id="reason"
                            wire:model="reason"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-primary focus:border-primary resize-none"
                            placeholder="Describe el motivo del ajuste de créditos..."
                        ></textarea>
                        @error('reason')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Preview -->
                    @if($user && $creditsChange > 0)
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Vista Previa</h4>
                            <div class="text-sm text-gray-600">
                                <div class="flex justify-between">
                                    <span>Créditos actuales:</span>
                                    <span class="font-semibold">{{ number_format($user->credits) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ $operation === 'add' ? 'Agregar' : 'Quitar' }}:</span>
                                    <span class="font-semibold {{ $operation === 'add' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $operation === 'add' ? '+' : '-' }}{{ number_format($creditsChange) }}
                                    </span>
                                </div>
                                <hr class="my-2">
                                <div class="flex justify-between text-gray-900 font-semibold">
                                    <span>Nuevo total:</span>
                                    <span>
                                        @php
                                            $newTotal = $operation === 'add' 
                                                ? $user->credits + $creditsChange 
                                                : $user->credits - $creditsChange;
                                        @endphp
                                        {{ number_format(max(0, $newTotal)) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Error Display -->
                    @error('user')
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                            <p class="text-red-600 text-sm">{{ $message }}</p>
                        </div>
                    @enderror

                    <!-- Modal Footer -->
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button 
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                            wire:loading.attr="disabled"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="submit"
                            class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                            wire:loading.attr="disabled"
                            wire:target="updateCredits"
                        >
                            <div wire:loading wire:target="updateCredits" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                            <span wire:loading.remove wire:target="updateCredits">Actualizar Créditos</span>
                            <span wire:loading wire:target="updateCredits">Actualizando...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
