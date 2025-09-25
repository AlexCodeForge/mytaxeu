<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                Gestión de Suscripciones
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Administra tu plan de suscripción y créditos para el procesamiento de archivos CSV.
            </p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <div class="text-right">
                <p class="text-sm text-gray-500">Créditos disponibles</p>
                <p class="text-2xl font-bold text-indigo-600">{{ $this->currentCredits }}</p>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="rounded-md bg-green-50 p-4 mb-6">
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

    @if (session()->has('error'))
        <div class="rounded-md bg-red-50 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif


    <!-- Discount Code Section -->
    @if(!$currentSubscription)
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl mb-8">
            <div class="px-4 py-6 sm:p-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">
                        <i class="fas fa-tag mr-2 text-indigo-600"></i>
                        Código de Descuento
                    </h3>
                    @if(!$showDiscountField)
                        <button wire:click="toggleDiscountField"
                                class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                            ¿Tienes un código de descuento?
                        </button>
                    @endif
                </div>

                @if($showDiscountField)
                    <div class="space-y-4">
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <input wire:model="discountCode"
                                       type="text"
                                       placeholder="Ingresa tu código de descuento"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('discountCode') border-red-300 @enderror">
                                @error('discountCode')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <button wire:click="applyDiscountCode"
                                    wire:loading.attr="disabled"
                                    wire:target="applyDiscountCode"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                                <span wire:loading.remove wire:target="applyDiscountCode">Aplicar</span>
                                <span wire:loading wire:target="applyDiscountCode">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>Validando...
                                </span>
                            </button>
                        </div>

                        <button wire:click="toggleDiscountField"
                                class="text-sm text-gray-500 hover:text-gray-700">
                            Cancelar
                        </button>
                    </div>
                @endif

                <!-- Applied Discount -->
                @if($appliedDiscount && $appliedDiscount['valid'])
                    <div class="mt-4 rounded-md bg-green-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <h4 class="text-sm font-medium text-green-800">
                                    ¡Código aplicado exitosamente!
                                </h4>
                                <div class="mt-2 text-sm text-green-700">
                                    <p><strong>{{ $appliedDiscount['code'] }}</strong> - {{ $appliedDiscount['name'] }}</p>
                                    <p>Descuento: <strong>{{ $appliedDiscount['type'] === 'percentage' ? $appliedDiscount['value'] . '%' : '€' . number_format($appliedDiscount['value'], 2) }}</strong></p>
                                    @if(isset($appliedDiscount['discount_amount']))
                                        <p>Ahorras: <strong>€{{ number_format($appliedDiscount['discount_amount'], 2) }}</strong></p>
                                    @else
                                        <p>El descuento se aplicará al seleccionar un plan compatible</p>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-3">
                                <button wire:click="removeDiscountCode"
                                        class="text-green-600 hover:text-green-800">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Discount Messages -->
                @if (session()->has('discount_success'))
                    <div class="mt-4 rounded-md bg-green-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700">{{ session('discount_success') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if (session()->has('discount_error'))
                    <div class="mt-4 rounded-md bg-red-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700">{{ session('discount_error') }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Available Plans -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
        <div class="px-4 py-6 sm:p-8">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-6">
                @if($currentSubscription)
                    Cambiar Plan
                @else
                    Planes Disponibles
                @endif
                <span class="text-sm text-gray-500">({{ count($availablePlans) }} planes encontrados)</span>
            </h3>

            @if(empty($availablePlans))
                <div class="text-center py-8">
                    <div class="text-gray-500">
                        <i class="fas fa-exclamation-triangle text-4xl mb-4"></i>
                        <p class="text-lg font-medium">No se encontraron planes activos</p>
                        <p class="text-sm">Por favor, contacta al administrador para activar los planes de suscripción.</p>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                @foreach($availablePlans as $plan)
                    <div class="relative rounded-2xl border p-8 shadow-sm flex flex-col
                        @if($this->isCurrentPlan($plan['id']))
                            border-green-500 ring-2 ring-green-500 bg-green-50/30
                        @elseif($plan['is_featured'])
                            border-indigo-600 ring-2 ring-indigo-600
                        @else
                            border-gray-200
                        @endif">

                        @if($this->isCurrentPlan($plan['id']))
                            <div class="absolute -top-5 left-0 right-0 mx-auto w-32 rounded-full bg-green-600 px-3 py-2 text-sm font-medium text-white text-center">
                                Plan Actual
                            </div>
                        @elseif($plan['is_featured'])
                            <div class="absolute -top-5 left-0 right-0 mx-auto w-32 rounded-full bg-indigo-600 px-3 py-2 text-sm font-medium text-white text-center">
                                Recomendado
                            </div>
                        @endif

                        <div class="flex-1">
                            <h3 class="text-xl font-semibold text-gray-900">{{ $plan['name'] }}</h3>
                            <div class="mt-4">
                                @php
                                    $discountInfo = $this->calculateDiscountForPlan($plan['id']);
                                @endphp
                                @if($discountInfo['applicable'])
                                    <!-- Discounted Price -->
                                    <div class="flex items-baseline text-gray-900">
                                        <span class="text-5xl font-bold tracking-tight text-green-600">€{{ number_format($discountInfo['final_amount'], 0) }}</span>
                                        <span class="ml-1 text-xl font-semibold text-green-600">/mes</span>
                                    </div>
                                    <div class="flex items-center mt-2">
                                        <span class="text-lg text-gray-500 line-through">€{{ number_format($plan['price'], 0) }}</span>
                                        <span class="ml-2 inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                            -{{ $appliedDiscount['type'] === 'percentage' ? $appliedDiscount['value'] . '%' : '€' . number_format($discountInfo['discount_amount'], 2) }}
                                        </span>
                                    </div>
                                @else
                                    <!-- Regular Price -->
                                    <div class="flex items-baseline text-gray-900">
                                        <span class="text-5xl font-bold tracking-tight">€{{ number_format($plan['price'], 0) }}</span>
                                        <span class="ml-1 text-xl font-semibold">/mes</span>
                                        @if($appliedDiscount && $appliedDiscount['valid'] && !$discountInfo['applicable'])
                                            <span class="ml-2 text-xs text-gray-500">(código no aplicable)</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <p class="mt-6 text-gray-500">{{ $plan['description'] }}</p>

                            <!-- Features -->
                            <ul role="list" class="mt-8 space-y-3 text-sm leading-6 text-gray-600">
                                @foreach($plan['features'] as $feature)
                                    <li class="flex gap-x-3">
                                        <svg class="h-6 w-5 flex-none text-indigo-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                        </svg>
                                        {{ $feature }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <!-- Action Button -->
                        <div class="mt-8">
                            @if($this->isCurrentPlan($plan['id']))
                                <!-- Current Plan Button - Disabled -->
                                <button disabled
                                        class="w-full rounded-md px-3 py-2 text-center text-sm font-semibold bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20 cursor-not-allowed">
                                    <svg class="inline-block w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                    </svg>
                                    Plan Actual
                                </button>
                            @else
                                <!-- Subscribe/Change Plan Button -->
                                @if($currentSubscription)
                                    @if($currentSubscription['status'] === 'active')
                                        <button wire:click="subscribe('{{ $plan['id'] }}')"
                                                wire:loading.attr="disabled"
                                                @disabled($this->isPlanButtonDisabled($plan['id']))
                                                class="w-full rounded-md px-3 py-2 text-center text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:cursor-not-allowed
                                                    @if($plan['id'] === 'professional') bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-indigo-600
                                                    @else bg-white text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 @endif">
                                            <span wire:loading.remove wire:target="subscribe('{{ $plan['id'] }}')">{{ $this->getPlanButtonText($plan['id']) }}</span>
                                            <span wire:loading wire:target="subscribe('{{ $plan['id'] }}')">Procesando...</span>
                                        </button>
                                    @else
                                        <button wire:click="subscribe('{{ $plan['id'] }}')"
                                                wire:loading.attr="disabled"
                                                @disabled($this->isPlanButtonDisabled($plan['id']))
                                                class="w-full rounded-md px-3 py-2 text-center text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:cursor-not-allowed
                                                    @if($plan['id'] === 'professional') bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-indigo-600
                                                    @else bg-white text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 @endif">
                                            <span wire:loading.remove wire:target="subscribe('{{ $plan['id'] }}')">Suscribirse</span>
                                            <span wire:loading wire:target="subscribe('{{ $plan['id'] }}')">Procesando...</span>
                                        </button>
                                    @endif
                                @else
                                    @if($plan['id'] === 'free')
                                        <!-- Free Plan - No button needed as it's assigned by default -->
                                        <div class="w-full rounded-md px-3 py-2 text-center text-sm font-medium bg-gray-50 text-gray-600 border border-gray-200">
                                            <svg class="inline-block w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                            </svg>
                                            Plan por Defecto
                                        </div>
                                    @else
                                        <button wire:click="subscribe('{{ $plan['id'] }}')"
                                                wire:loading.attr="disabled"
                                                @disabled($this->isPlanButtonDisabled($plan['id']))
                                                class="w-full rounded-md px-3 py-2 text-center text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:cursor-not-allowed
                                                    @if($plan['id'] === 'professional') bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-indigo-600
                                                    @else bg-white text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 @endif">
                                            <span wire:loading.remove wire:target="subscribe('{{ $plan['id'] }}')">Comenzar</span>
                                            <span wire:loading wire:target="subscribe('{{ $plan['id'] }}')">Procesando...</span>
                                        </button>
                                    @endif
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Additional Information -->
            <div class="mt-8 border-t border-gray-200 pt-8">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">Términos de Facturación</h4>
                        <ul class="mt-3 text-sm text-gray-600 space-y-1">
                            <li>• Facturación mensual automática</li>
                            <li>• Cancelación permitida en cualquier momento</li>
                            <li>• Los créditos no utilizados no se acumulan</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">Métodos de Pago</h4>
                        <div class="mt-3 flex items-center space-x-3">
                            <div class="flex items-center bg-indigo-50 px-3 py-2 rounded-lg">
                                <svg class="h-8 w-8 text-indigo-600" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                                    <path d="M165 144.7l-43.3 9.2-.2 142.4c0 26.3 19.8 43.3 46.1 43.3 14.6 0 25.3-2.7 31.2-5.9v-33.8c-5.7 2.3-33.7 10.5-33.7-15.7V221h33.7v-37.8h-33.7zm89.1 51.6l-2.7-13.1H213v153.2h44.3V233.3c10.5-13.8 28.2-11.1 33.9-9.3v-40.8c-6-2.1-26.7-6-37.1 13.1zm92.3-72.3l-44.6 9.5v36.2l44.6-9.5zM44.9 228.3c0-6.9 5.8-9.6 15.1-9.7 13.5 0 30.7 4.1 44.2 11.4v-41.8c-14.7-5.8-29.4-8.1-44.1-8.1-36 0-60 18.8-60 50.2 0 49.2 67.5 41.2 67.5 62.4 0 8.2-7.1 10.9-17 10.9-14.7 0-33.7-6.1-48.6-14.2v40c16.5 7.1 33.2 10.1 48.5 10.1 36.9 0 62.3-15.8 62.3-47.8 0-52.9-67.9-43.4-67.9-63.4zM640 261.6c0-45.5-22-81.4-64.2-81.4s-67.9 35.9-67.9 81.1c0 53.5 30.3 78.2 73.5 78.2 21.2 0 37.1-4.8 49.2-11.5v-33.4c-12.1 6.1-26 9.8-43.6 9.8-17.3 0-32.5-6.1-34.5-26.9h86.9c.2-2.3.6-11.6.6-15.9zm-87.9-16.8c0-20 12.3-28.4 23.4-28.4 10.9 0 22.5 8.4 22.5 28.4zm-112.9-64.6c-17.4 0-28.6 8.2-34.8 13.9l-2.3-11H363v204.8l44.4-9.4.1-50.2c6.4 4.7 15.9 11.2 31.4 11.2 31.8 0 60.8-23.2 60.8-79.6.1-51.6-29.3-79.7-60.5-79.7zm-10.6 122.5c-10.4 0-16.6-3.8-20.9-8.4l-.3-66c4.6-5.1 11-8.8 21.2-8.8 16.2 0 27.4 18.2 27.4 41.4.1 23.9-10.9 41.8-27.4 41.8zm-126.7 33.7h44.6V183.2h-44.6z"/>
                                </svg>

                            </div>
                            <div class="text-xs text-gray-500">
                                <div>Acepta todas las tarjetas principales</div>
                                <div>Pagos seguros con cifrado SSL</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
