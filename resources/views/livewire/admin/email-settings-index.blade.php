<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Configuración de Emails</h1>
            <p class="text-gray-600 mt-1">Gestiona todas las configuraciones del sistema de emails</p>
        </div>
        <div class="flex space-x-3">
            <button
                wire:click="refreshSettings"
                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors"
            >
                <i class="fas fa-sync-alt mr-2"></i>Actualizar
            </button>
            <button
                wire:click="openTestModal"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors"
            >
                <i class="fas fa-paper-plane mr-2"></i>Probar Emails
            </button>
            <button
                wire:click="resetAllSettings"
                wire:confirm="¿Estás seguro de que quieres restablecer todas las configuraciones?"
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors"
            >
                <i class="fas fa-undo mr-2"></i>Restablecer Todo
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

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-cog text-blue-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Configuraciones</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['total_settings'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Activas</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['active_settings'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-folder text-purple-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Categorías</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $stats['categories_count'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-clock text-orange-600 text-2xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Última Actualización</p>
                    <p class="text-sm font-bold text-gray-900">{{ $stats['last_updated'] ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach ($settingsByCategory as $category => $settings)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">
                                {{ $categories[$category] ?? ucfirst($category) }}
                            </h3>
                            <p class="text-sm text-gray-500">{{ count($settings) }} configuraciones</p>
                        </div>
                        <div class="flex space-x-2">
                            <button
                                wire:click="$dispatch('open-category-test', { category: '{{ $category }}' })"
                                class="text-sm bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full hover:bg-yellow-200 transition-colors"
                            >
                                <i class="fas fa-flask mr-1"></i>Probar
                            </button>
                            <a
                                href="{{ route('admin.email-settings.edit', $category) }}"
                                wire:navigate
                                class="text-sm bg-blue-100 text-blue-800 px-3 py-1 rounded-full hover:bg-blue-200 transition-colors"
                            >
                                <i class="fas fa-edit mr-1"></i>Editar
                            </a>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4">
                    <div class="space-y-3">
                        @foreach ($settings as $setting)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900">{{ $setting['label'] }}</p>
                                    @if ($setting['description'])
                                        <p class="text-xs text-gray-500">{{ $setting['description'] }}</p>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <span class="text-sm {{ $setting['is_active'] ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $setting['display_value'] }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Email Test Modal -->
    @if ($showTestModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" wire:click="closeTestModal">
            <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 xl:w-1/2 shadow-lg rounded-md bg-white" wire:click.stop>
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-paper-plane mr-2 text-green-600"></i>Probar Envío de Emails
                    </h3>
                    <button wire:click="closeTestModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="py-4">
                    @if (!$isLoading && !$showResults)
                        <!-- Email Testing Form -->
                        <form wire:submit="sendTestEmails">
                            <div class="space-y-4">
                                <!-- Email Input -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="fas fa-envelope mr-1"></i>Email de Destino
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
                                        <i class="fas fa-list-check mr-1"></i>Tipos de Email a Enviar
                                    </label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-64 overflow-y-auto border border-gray-200 rounded-md p-4">
                                        @foreach ($emailTypeLabels as $type => $label)
                                            <label class="flex items-center space-x-2">
                                                <input
                                                    type="checkbox"
                                                    wire:model="selectedEmailTypes"
                                                    value="{{ $type }}"
                                                    class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded"
                                                >
                                                <span class="text-sm text-gray-700">{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('selectedEmailTypes')
                                        <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                    <p class="text-xs text-gray-500 mt-1">
                                        Seleccionados: {{ count($selectedEmailTypes) }} emails
                                    </p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                                <button
                                    type="button"
                                    wire:click="closeTestModal"
                                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
                                >
                                    <i class="fas fa-times mr-1"></i>Cancelar
                                </button>
                                <button
                                    type="submit"
                                    class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors font-medium"
                                    @if(count($selectedEmailTypes) === 0) disabled @endif
                                >
                                    <i class="fas fa-paper-plane mr-1"></i>Enviar {{ count($selectedEmailTypes) }} Emails
                                </button>
                            </div>
                        </form>

                    @elseif ($isLoading)
                        <!-- Loading State -->
                        <div class="text-center py-12">
                            <div class="text-blue-600 mb-4">
                                <i class="fas fa-spinner fa-spin text-4xl"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">
                                Enviando Emails de Prueba...
                            </h4>
                            <p class="text-gray-600 mb-4">
                                Enviando {{ count($selectedEmailTypes) }} emails a <strong>{{ $testEmail }}</strong>
                            </p>
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                                <p class="text-sm text-blue-700">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Por favor espera mientras se procesan los emails...
                                </p>
                            </div>
                        </div>

                    @elseif ($showResults)
                        <!-- Results Display -->
                        <div class="py-4">
                            <div class="bg-{{ $testResults['success'] ? 'green' : 'red' }}-50 border border-{{ $testResults['success'] ? 'green' : 'red' }}-200 rounded-md p-4 mb-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        @if ($testResults['success'])
                                            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                                        @else
                                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                                        @endif
                                    </div>
                                    <div class="ml-3">
                                        <h4 class="text-lg font-medium text-{{ $testResults['success'] ? 'green' : 'red' }}-800 mb-2">
                                            {{ $testResults['message'] }}
                                        </h4>

                                        @if (!empty($testResults['sent_emails']))
                                            <div class="mb-3">
                                                <p class="text-sm font-medium text-green-700 mb-1">
                                                    <i class="fas fa-check mr-1"></i>Emails enviados exitosamente:
                                                </p>
                                                <ul class="list-disc list-inside text-sm text-green-600 space-y-1">
                                                    @foreach ($testResults['sent_emails'] as $email)
                                                        <li>{{ $email }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @if (!empty($testResults['errors']))
                                            <div class="mb-3">
                                                <p class="text-sm font-medium text-red-700 mb-1">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>Errores encontrados:
                                                </p>
                                                <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                                                    @foreach ($testResults['errors'] as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <div class="text-sm text-gray-600">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Revisa tu bandeja de entrada en <strong>{{ $testEmail }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end space-x-3">
                                <button
                                    wire:click="resetTestForm"
                                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors"
                                >
                                    <i class="fas fa-redo mr-1"></i>Enviar Más
                                </button>
                                <button
                                    wire:click="closeTestModal"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                                >
                                    <i class="fas fa-check mr-1"></i>Cerrar
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
