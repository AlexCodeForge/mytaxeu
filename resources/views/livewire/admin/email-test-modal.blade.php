<div>
    <!-- Modal Backdrop -->
    @if ($show)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" wire:click="closeModal">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white" wire:click.stop>
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        @if ($testType === 'send_emails')
                            <i class="fas fa-paper-plane mr-2 text-green-600"></i>Probar Envío de Emails
                        @else
                            <i class="fas fa-flask mr-2 text-yellow-600"></i>Diagnóstico de Configuración
                        @endif
                    </h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="py-4">
                    @if ($testType === 'send_emails')
                        <!-- Email Testing Form -->
                        <form wire:submit="runTest">
                            <div class="space-y-4">
                                <!-- Email Input -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Email de Destino
                                    </label>
                                    <input
                                        type="email"
                                        wire:model="testEmail"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                        placeholder="ejemplo@correo.com"
                                        required
                                    >
                                    @error('testEmail')
                                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Email Types Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Tipos de Email a Probar
                                    </label>
                                    <div class="grid grid-cols-2 gap-2 max-h-60 overflow-y-auto border border-gray-200 rounded-md p-3">
                                        @foreach ($emailTypeLabels as $type => $label)
                                            <label class="flex items-center">
                                                <input
                                                    type="checkbox"
                                                    wire:model="selectedEmailTypes"
                                                    value="{{ $type }}"
                                                    class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded"
                                                >
                                                <span class="ml-2 text-sm text-gray-700">{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                                <button
                                    type="button"
                                    wire:click="closeModal"
                                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50"
                                >
                                    <span wire:loading.remove wire:target="runTest">
                                        <i class="fas fa-paper-plane mr-1"></i>Enviar Emails
                                    </span>
                                    <span wire:loading wire:target="runTest">
                                        <i class="fas fa-spinner fa-spin mr-1"></i>Enviando...
                                    </span>
                                </button>
                            </div>
                        </form>

                    @else
                        <!-- Diagnostic Testing -->
                        <div class="text-center py-6">
                            <div class="text-yellow-600 mb-4">
                                <i class="fas fa-flask text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">
                                Diagnóstico de Configuración
                            </h4>
                            <p class="text-gray-600 mb-6">
                                Se validarán todas las configuraciones de la categoría "{{ $category }}"
                            </p>

                            <div class="flex justify-center space-x-3">
                                <button
                                    wire:click="closeModal"
                                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
                                >
                                    Cancelar
                                </button>
                                <button
                                    wire:click="runTest"
                                    class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50"
                                >
                                    <span wire:loading.remove wire:target="runTest">
                                        <i class="fas fa-flask mr-1"></i>Ejecutar Diagnóstico
                                    </span>
                                    <span wire:loading wire:target="runTest">
                                        <i class="fas fa-spinner fa-spin mr-1"></i>Ejecutando...
                                    </span>
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Loading State -->
                    @if ($isLoading)
                        <div class="text-center py-8">
                            <i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4"></i>
                            <p class="text-gray-600">
                                @if ($testType === 'send_emails')
                                    Enviando emails de prueba...
                                @else
                                    Ejecutando diagnóstico...
                                @endif
                            </p>
                        </div>
                    @endif

                    <!-- Test Results -->
                    @if ($showResults && !empty($testResults))
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <h4 class="text-lg font-medium text-gray-900 mb-3">Resultados</h4>

                            <div class="bg-{{ $testResults['success'] ? 'green' : 'red' }}-50 border border-{{ $testResults['success'] ? 'green' : 'red' }}-200 rounded-md p-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        @if ($testResults['success'])
                                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                        @else
                                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                                        @endif
                                    </div>
                                    <div class="ml-3">
                                        <h5 class="text-sm font-medium text-{{ $testResults['success'] ? 'green' : 'red' }}-800">
                                            {{ $testResults['message'] }}
                                        </h5>

                                        @if (isset($testResults['sent_emails']) && !empty($testResults['sent_emails']))
                                            <div class="mt-2">
                                                <p class="text-sm text-green-700 font-medium">Emails enviados exitosamente:</p>
                                                <ul class="list-disc list-inside text-sm text-green-600 mt-1">
                                                    @foreach ($testResults['sent_emails'] as $email)
                                                        <li>{{ $email }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if (isset($testResults['errors']) && !empty($testResults['errors']))
                                            <div class="mt-2">
                                                <p class="text-sm text-red-700 font-medium">Errores encontrados:</p>
                                                <ul class="list-disc list-inside text-sm text-red-600 mt-1">
                                                    @foreach ($testResults['errors'] as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if (isset($testResults['details']) && !empty($testResults['details']))
                                            <div class="mt-2">
                                                <p class="text-sm text-gray-700 font-medium">Detalles del diagnóstico:</p>
                                                <ul class="list-none text-sm text-gray-600 mt-1 space-y-1">
                                                    @foreach ($testResults['details'] as $detail)
                                                        <li class="font-mono">{{ $detail }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
