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

    <!-- Current Subscription Status -->
    @if($currentSubscription)
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl mb-8">
            <div class="px-4 py-6 sm:p-8">
                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">
                    Suscripción Actual
                </h3>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xl font-semibold text-gray-900">{{ $currentSubscription['name'] }}</p>
                        <p class="text-sm text-gray-500">
                            Estado:
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium
                                @if($currentSubscription['status'] === 'active') bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20
                                @elseif($currentSubscription['status'] === 'canceled') bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20
                                @else bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20 @endif">
                                {{ ucfirst($currentSubscription['status']) }}
                            </span>
                        </p>
                        <p class="text-sm text-gray-500 mt-1">
                            @if($currentSubscription['cancel_at_period_end'])
                                Se cancelará el {{ \Carbon\Carbon::createFromTimestamp($currentSubscription['current_period_end'])->format('d/m/Y') }}
                            @else
                                Próxima renovación: {{ \Carbon\Carbon::createFromTimestamp($currentSubscription['current_period_end'])->format('d/m/Y') }}
                            @endif
                        </p>
                    </div>

                    <div class="flex gap-3">
                        <button wire:click="viewBillingPortal"
                                class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                            <svg class="-ml-0.5 mr-1.5 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.25 2A2.25 2.25 0 002 4.25v11.5A2.25 2.25 0 004.25 18h11.5A2.25 2.25 0 0018 15.75V4.25A2.25 2.25 0 0015.75 2H4.25zm4.03 6.28a.75.75 0 00-1.06-1.06L6 8.44l-1.22-1.22a.75.75 0 00-1.06 1.06L5.44 10l-1.72 1.72a.75.75 0 101.06 1.06L6 11.56l1.22 1.22a.75.75 0 001.06-1.06L6.56 10l1.72-1.72z" clip-rule="evenodd" />
                            </svg>
                            Portal de Facturación
                        </button>
                    </div>
                </div>

                <!-- Renewal Preference -->
                @if($currentSubscription['status'] === 'active')
                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">Renovación Automática</h4>
                                <p class="text-sm text-gray-500">
                                    @if($willRenew)
                                        Tu suscripción se renovará automáticamente cada mes.
                                    @else
                                        Tu suscripción se cancelará al final del período actual.
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center">
                                <input wire:model.live="willRenew"
                                       wire:change="updateRenewalPreference"
                                       type="checkbox"
                                       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                                <label class="ml-2 text-sm text-gray-600">Renovar automáticamente</label>
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
            </h3>

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
                            <p class="mt-4 flex items-baseline text-gray-900">
                                <span class="text-5xl font-bold tracking-tight">€{{ number_format($plan['price'], 0) }}</span>
                                <span class="ml-1 text-xl font-semibold">/mes</span>
                            </p>
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
                            <li>• Compromiso mínimo de 3 meses</li>
                            <li>• Cancelación permitida en cualquier momento</li>
                            <li>• Los créditos no utilizados no se acumulan</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-900">Métodos de Pago</h4>
                        <div class="mt-3 flex items-center space-x-3">
                            <div class="flex items-center">
                                <svg class="h-8 w-8 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M7.076 21c.177.04.354.04.531 0l14.479-3.69c.454-.116.7-.635.584-1.089L18.531 3.789c-.116-.454-.635-.7-1.089-.584L3.443 6.895c-.454.116-.7.635-.584 1.089l3.13 12.433c.112.448.52.726.968.726.04 0 .082-.002.119-.007z"/>
                                </svg>
                                <span class="ml-1 text-sm text-gray-600">Visa</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="h-8 w-8 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M7.076 21c.177.04.354.04.531 0l14.479-3.69c.454-.116.7-.635.584-1.089L18.531 3.789c-.116-.454-.635-.7-1.089-.584L3.443 6.895c-.454.116-.7.635-.584 1.089l3.13 12.433c.112.448.52.726.968.726.04 0 .082-.002.119-.007z"/>
                                </svg>
                                <span class="ml-1 text-sm text-gray-600">Mastercard</span>
                            </div>
                            <div class="flex items-center">
                                <svg class="h-8 w-8 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M7.076 21c.177.04.354.04.531 0l14.479-3.69c.454-.116.7-.635.584-1.089L18.531 3.789c-.116-.454-.635-.7-1.089-.584L3.443 6.895c-.454.116-.7.635-.584 1.089l3.13 12.433c.112.448.52.726.968.726.04 0 .082-.002.119-.007z"/>
                                </svg>
                                <span class="ml-1 text-sm text-gray-600">SEPA</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
