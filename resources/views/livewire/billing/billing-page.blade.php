<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Facturación</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Gestiona tu suscripción, facturación y créditos
        </p>
    </div>

    <!-- Flash Messages -->
    @if (session('portal_return'))
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg dark:bg-green-900/10 dark:border-green-800 dark:text-green-400">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm">Has regresado del portal de facturación.</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900/10 dark:border-red-800 dark:text-red-400">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session('info'))
        <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg dark:bg-blue-900/10 dark:border-blue-800 dark:text-blue-400">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm">{{ session('info') }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Current Plan & Credits -->
        <div class="lg:col-span-2">
            <!-- Current Plan Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ $planName }}
                        </h2>
                        <button 
                            wire:click="refreshSubscriptionStatus"
                            class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                            title="Actualizar estado">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Subscription Status -->
                    <div class="mb-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($hasActiveSubscription && $subscriptionStatus === 'active') bg-green-100 text-green-800 dark:bg-green-900/10 dark:text-green-400
                            @elseif($subscriptionStatus === 'trialing') bg-blue-100 text-blue-800 dark:bg-blue-900/10 dark:text-blue-400
                            @elseif($subscriptionStatus === 'canceled') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/10 dark:text-yellow-400
                            @elseif($subscriptionStatus === 'past_due') bg-red-100 text-red-800 dark:bg-red-900/10 dark:text-red-400
                            @else bg-gray-100 text-gray-800 dark:bg-gray-900/10 dark:text-gray-400
                            @endif">
                            {{ $statusMessage }}
                        </span>
                    </div>

                    <!-- Next Billing Date -->
                    @if($nextBillingDate && $hasActiveSubscription)
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Próximo pago:</span> {{ $nextBillingDate }}
                            </p>
                        </div>
                    @endif

                    <!-- Plan Features -->
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Características incluidas:</h3>
                        <ul class="space-y-1">
                            @foreach($planFeatures as $feature)
                                <li class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                    <svg class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        @if($showManageButton)
                            <button 
                                wire:click="redirectToPortal"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-blue-500 dark:hover:bg-blue-600">
                                <span wire:loading.remove>Gestionar Plan</span>
                                <span wire:loading class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Cargando...
                                </span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Upgrade CTA for non-subscribers -->
            @if($showUpgradeCta)
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6 text-white">
                        <h2 class="text-2xl font-bold mb-2">¡Actualiza tu plan!</h2>
                        <p class="text-blue-100 mb-4">Obtén más créditos y funciones avanzadas para gestionar más clientes de manera eficiente.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                            @foreach($availablePlans as $plan)
                                <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 border border-white/20">
                                    <h3 class="font-semibold text-lg mb-1">{{ $plan['name'] }}</h3>
                                    <p class="text-blue-100 text-sm mb-2">{{ $plan['description'] }}</p>
                                    <div class="flex items-baseline mb-3">
                                        <span class="text-2xl font-bold">€{{ number_format($plan['price'], 0) }}</span>
                                        <span class="text-blue-100 text-sm ml-1">/mes</span>
                                    </div>
                                    <div class="text-sm text-blue-100">
                                        <div class="flex items-center mb-1">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            {{ $plan['credits'] }} créditos/mes
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            <a href="{{ route('billing.subscriptions') }}" 
                               class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Elegir Plan
                                <svg class="ml-2 -mr-1 w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Credits Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Créditos Disponibles</h3>
                    
                    <div class="text-center">
                        <div class="text-4xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                            {{ $currentCredits }}
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            créditos restantes
                        </p>
                    </div>

                    @if($currentCredits <= 5)
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md dark:bg-yellow-900/10 dark:border-yellow-800">
                            <p class="text-sm text-yellow-700 dark:text-yellow-400">
                                <strong>Atención:</strong> Te quedan pocos créditos. 
                                @if(!$hasActiveSubscription)
                                    Considera actualizar tu plan.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Acciones Rápidas</h3>
                    
                    <div class="space-y-3">
                        @if($hasActiveSubscription)
                            <a href="#" 
                               class="w-full flex items-center justify-between p-3 text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600">
                                <span>Ver historial de pagos</span>
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            
                            <a href="#" 
                               class="w-full flex items-center justify-between p-3 text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600">
                                <span>Descargar facturas</span>
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        @endif
                        
                        <a href="{{ route('dashboard') }}" 
                           class="w-full flex items-center justify-between p-3 text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 rounded-md hover:bg-gray-100 dark:hover:bg-gray-600">
                            <span>Volver al panel</span>
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
