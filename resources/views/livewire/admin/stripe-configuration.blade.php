<div class="space-y-6">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                Configuración de Stripe
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Configure las claves API de Stripe para habilitar el sistema de suscripciones y pagos.
            </p>
        </div>
    </div>

    <!-- Configuration Status -->
    @if($configStatus['configured'])
        <!-- Valid configuration -->
        <div class="rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.53a.75.75 0 00-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">
                        Stripe configurado correctamente
                    </h3>
                    <div class="mt-2 text-sm text-green-700">
                        <p>Las claves API están configuradas y el sistema está listo para procesar pagos.</p>
                        @if($configStatus['test_mode'])
                            <p class="mt-1 font-medium">⚠️ Modo de prueba activado</p>
                        @endif
                        <div class="mt-2 text-xs text-green-600">
                            <p>✓ Clave pública: {{ $configStatus['public_key_preview'] }}</p>
                            <p>✓ Clave secreta: {{ $configStatus['secret_key_preview'] }}</p>
                            @if($configStatus['has_webhook_secret'])
                                <p>✓ Webhook secret configurado</p>
                            @endif
                        </div>
                        <div class="mt-3">
                            <button type="button" wire:click="testCurrentConfiguration"
                                    wire:loading.attr="disabled" wire:target="testCurrentConfiguration"
                                    class="inline-flex items-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600 disabled:opacity-50">
                                <svg wire:loading wire:target="testCurrentConfiguration" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="testCurrentConfiguration">Probar Configuración</span>
                                <span wire:loading wire:target="testCurrentConfiguration">Probando...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @elseif($configStatus['has_keys'] && !$configStatus['is_valid'])
        <!-- Keys exist but are invalid -->
        <div class="rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">
                        Error en la configuración de Stripe
                    </h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p>{{ $configStatus['validation_error'] }}</p>
                        @if($configStatus['test_mode'])
                            <p class="mt-2 text-xs">Modo de prueba: Asegúrate de usar claves que comiencen con <code class="bg-red-100 px-1 rounded">pk_test_</code> y <code class="bg-red-100 px-1 rounded">sk_test_</code></p>
                        @else
                            <p class="mt-2 text-xs">Modo producción: Asegúrate de usar claves que comiencen con <code class="bg-red-100 px-1 rounded">pk_live_</code> y <code class="bg-red-100 px-1 rounded">sk_live_</code></p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- No configuration -->
        <div class="rounded-md bg-yellow-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">
                        Configuración de Stripe pendiente
                    </h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Configure las claves API de Stripe para habilitar el sistema de suscripciones.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.53a.75.75 0 00-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-green-700">{{ session('message') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('test-success'))
        <div class="rounded-md bg-blue-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.53a.75.75 0 00-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">{{ session('test-success') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Error Messages -->
    @error('general')
        <div class="rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ $message }}</p>
                </div>
            </div>
        </div>
    @enderror

    <!-- Configuration Form -->
    <form wire:submit="saveConfiguration" class="space-y-6">
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl md:col-span-2">
            <div class="px-4 py-6 sm:p-8">
                <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                    <!-- Test Mode Toggle -->
                    <div class="sm:col-span-6">
                        <div class="relative flex items-start">
                            <div class="flex h-6 items-center">
                                <input wire:model="testMode" id="testMode" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                            </div>
                            <div class="ml-3 text-sm leading-6">
                                <label for="testMode" class="font-medium text-gray-900">Modo de prueba</label>
                                <p class="text-gray-500">Usar claves de prueba de Stripe (recomendado para desarrollo)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Public Key -->
                    <div class="sm:col-span-6">
                        <label for="publicKey" class="block text-sm font-medium leading-6 text-gray-900">
                            Clave Pública <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-2">
                            <input wire:model="publicKey" type="text" id="publicKey"
                                   class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                   placeholder="pk_test_... o pk_live_...">
                        </div>
                        @error('publicKey')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-sm text-gray-500">
                            La clave pública se usa en el frontend y es segura para compartir.
                        </p>
                    </div>

                    <!-- Secret Key -->
                    <div class="sm:col-span-6">
                        <label for="secretKey" class="block text-sm font-medium leading-6 text-gray-900">
                            Clave Secreta <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-2 relative">
                            <input wire:model="secretKey"
                                   type="{{ $showSecretKey ? 'text' : 'password' }}"
                                   id="secretKey"
                                   class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 pr-10"
                                   placeholder="sk_test_... o sk_live_...">
                            <button type="button" wire:click="toggleSecretKeyVisibility"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3">
                                @if($showSecretKey)
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                                    </svg>
                                @else
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                @endif
                            </button>
                        </div>
                        @error('secretKey')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('test')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-sm text-gray-500">
                            La clave secreta se almacena encriptada y se usa para las operaciones del servidor.
                        </p>

                        <!-- Test Connection Button -->
                        @if($secretKey)
                            <div class="mt-3">
                                <button type="button" wire:click="testConnection"
                                        wire:loading.attr="disabled" wire:target="testConnection"
                                        class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50">
                                    <svg wire:loading wire:target="testConnection" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="testConnection">Probar Conexión</span>
                                    <span wire:loading wire:target="testConnection">Probando...</span>
                                </button>
                            </div>
                        @endif
                    </div>

                    <!-- Webhook Secret -->
                    <div class="sm:col-span-6">
                        <label for="webhookSecret" class="block text-sm font-medium leading-6 text-gray-900">
                            Secreto del Webhook
                        </label>
                        <div class="mt-2 relative">
                            <input wire:model="webhookSecret"
                                   type="{{ $showWebhookSecret ? 'text' : 'password' }}"
                                   id="webhookSecret"
                                   class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 pr-10"
                                   placeholder="whsec_...">
                            <button type="button" wire:click="toggleWebhookSecretVisibility"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3">
                                @if($showWebhookSecret)
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21" />
                                    </svg>
                                @else
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                @endif
                            </button>
                        </div>
                        @error('webhookSecret')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-sm text-gray-500">
                            Opcional. Se usa para verificar la autenticidad de los webhooks de Stripe.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between gap-x-6 border-t border-gray-900/10 px-4 py-4 sm:px-8">
                <button type="button" wire:click="clearConfiguration"
                        wire:confirm="¿Estás seguro de que quieres eliminar toda la configuración de Stripe?"
                        class="text-sm font-semibold leading-6 text-red-600 hover:text-red-500">
                    Limpiar Configuración
                </button>

                <div class="flex gap-x-3">
                    <button type="submit"
                            wire:loading.attr="disabled" wire:target="saveConfiguration"
                            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50">
                        <svg wire:loading wire:target="saveConfiguration" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="saveConfiguration">Guardar Configuración</span>
                        <span wire:loading wire:target="saveConfiguration">Guardando...</span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Price IDs Configuration - COMMENTED OUT (no longer needed) -->
    {{--
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
        <div class="px-4 py-6 sm:p-8">
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div>
                    <h3 class="text-lg font-medium leading-6 text-gray-900">
                        Configuración de IDs de Precios
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Configure los IDs de precios de Stripe para los planes de suscripción.
                    </p>
                </div>
                @if($priceIdsStatus['configured'])
                    <div class="mt-3 flex rounded-md shadow-sm md:mt-0 md:ml-4">
                        <span class="inline-flex items-center px-3 py-2 rounded-md text-sm font-medium bg-green-100 text-green-800">
                            <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.53a.75.75 0 00-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                            {{ $priceIdsStatus['count'] }} de 3 configurados
                        </span>
                    </div>
                @endif
            </div>

            <form wire:submit.prevent="savePriceIds" class="space-y-6">
                <!-- Basic Plan Price ID -->
                <div>
                    <label for="basicPriceId" class="block text-sm font-medium text-gray-700">
                        Plan Básico - ID de Precio
                        @if($priceIdsStatus['has_basic'])
                            <span class="text-green-600 ml-1">✓</span>
                        @endif
                    </label>
                    <div class="mt-1">
                        <input type="text" id="basicPriceId" wire:model.defer="basicPriceId"
                               placeholder="price_1xxxxxxxxx"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    @error('basicPriceId')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">10 créditos mensuales - €29/mes</p>
                </div>

                <!-- Professional Plan Price ID -->
                <div>
                    <label for="professionalPriceId" class="block text-sm font-medium text-gray-700">
                        Plan Profesional - ID de Precio
                        @if($priceIdsStatus['has_professional'])
                            <span class="text-green-600 ml-1">✓</span>
                        @endif
                    </label>
                    <div class="mt-1">
                        <input type="text" id="professionalPriceId" wire:model.defer="professionalPriceId"
                               placeholder="price_1xxxxxxxxx"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    @error('professionalPriceId')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">25 créditos mensuales - €59/mes</p>
                </div>

                <!-- Enterprise Plan Price ID -->
                <div>
                    <label for="enterprisePriceId" class="block text-sm font-medium text-gray-700">
                        Plan Empresarial - ID de Precio
                        @if($priceIdsStatus['has_enterprise'])
                            <span class="text-green-600 ml-1">✓</span>
                        @endif
                    </label>
                    <div class="mt-1">
                        <input type="text" id="enterprisePriceId" wire:model.defer="enterprisePriceId"
                               placeholder="price_1xxxxxxxxx"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    @error('enterprisePriceId')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">50 créditos mensuales - €99/mes</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                    <div class="flex space-x-3">
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="savePriceIds"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                            <span wire:loading.remove wire:target="savePriceIds">Guardar IDs de Precios</span>
                            <span wire:loading wire:target="savePriceIds">Guardando...</span>
                        </button>

                        <button type="button" wire:click="loadCurrentPriceIds"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Cargar IDs Actuales
                        </button>
                    </div>

                    @if($priceIdsStatus['configured'])
                        <button type="button" wire:click="clearPriceIds"
                                wire:confirm="¿Está seguro de que desea eliminar todos los IDs de precios?"
                                class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Limpiar Todo
                        </button>
                    @endif
                </div>
            </form>

            <!-- Help Text -->
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h4 class="text-sm font-medium text-blue-800">Cómo obtener los IDs de precios:</h4>
                        <div class="mt-2 text-sm text-blue-700">
                            <ol class="list-decimal list-inside space-y-1">
                                <li>Ve a tu panel de Stripe → Productos</li>
                                <li>Selecciona el producto correspondiente</li>
                                <li>Copia el ID de precio (comienza con "price_")</li>
                                <li>O usa el comando: <code class="bg-blue-100 px-1 rounded">php artisan stripe:setup-products</code></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    --}}

    <!-- Webhook Configuration Guide -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
        <div class="px-4 py-6 sm:p-8">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">
                Configuración de Webhooks en Stripe
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">URL del Endpoint:</label>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <input type="text" readonly value="{{ $this->webhookUrl }}"
                               class="block w-full rounded-md border-gray-300 bg-gray-50 text-gray-500 sm:text-sm">
                        <button type="button" onclick="navigator.clipboard.writeText('{{ $this->webhookUrl }}')"
                                class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <span>Copiar</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Eventos Recomendados:</label>
                    <ul class="mt-2 text-sm text-gray-600 list-disc list-inside space-y-1">
                        @foreach($this->recommendedEvents as $event)
                            <li><code class="bg-gray-100 px-1 rounded">{{ $event }}</code></li>
                        @endforeach
                    </ul>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-blue-800">Instrucciones:</h4>
                            <div class="mt-2 text-sm text-blue-700">
                                <ol class="list-decimal list-inside space-y-1">
                                    <li>Ve a tu panel de Stripe → Desarrolladores → Webhooks</li>
                                    <li>Haz clic en "Agregar endpoint"</li>
                                    <li>Copia la URL del endpoint mostrada arriba</li>
                                    <li>Selecciona los eventos recomendados</li>
                                    <li>Copia el secreto del webhook y pégalo en el formulario arriba</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
