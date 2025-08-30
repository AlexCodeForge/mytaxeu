<div class="space-y-6">
    <!-- Credit Balance Card -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Créditos Disponibles</h3>
                    <div class="mt-2 flex items-baseline">
                        <p class="text-3xl font-bold text-indigo-600">{{ $creditBalance }}</p>
                        <p class="ml-2 text-sm text-gray-500">créditos</p>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Subscription Status -->
            @if($this->subscriptionStatus)
                <div class="mt-4 border-t border-gray-200 pt-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Estado de suscripción:</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($this->subscriptionStatus['status'] === 'active') bg-green-100 text-green-800
                            @else bg-yellow-100 text-yellow-800 @endif">
                            {{ ucfirst($this->subscriptionStatus['status']) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-sm mt-1">
                        <span class="text-gray-500">
                            @if($this->subscriptionStatus['cancel_at_period_end'])
                                Se cancela:
                            @else
                                Próxima renovación:
                            @endif
                        </span>
                        <span class="text-gray-900">
                            {{ \Carbon\Carbon::createFromTimestamp($this->subscriptionStatus['current_period_end'])->format('d/m/Y') }}
                        </span>
                    </div>
                </div>
            @else
                <div class="mt-4 border-t border-gray-200 pt-4">
                    <p class="text-sm text-gray-500">No tienes una suscripción activa.</p>
                    <a href="{{ route('billing.subscriptions') }}" class="text-sm text-indigo-600 hover:text-indigo-500">
                        Ver planes disponibles →
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Usage Statistics -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Uso de Créditos - Este Mes</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- Credits Used -->
                <div class="bg-red-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">Créditos Utilizados</p>
                            <p class="text-2xl font-bold text-red-900">{{ abs($creditsUsedThisMonth) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Credits Allocated -->
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">Créditos Recibidos</p>
                            <p class="text-2xl font-bold text-green-900">{{ $creditsAllocatedThisMonth }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Usage Progress Bar -->
            @if($creditsAllocatedThisMonth > 0)
                <div class="mt-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Porcentaje de uso este mes</span>
                        <span class="text-gray-900">{{ number_format($this->usagePercentage, 1) }}%</span>
                    </div>
                    <div class="mt-2">
                        <div class="bg-gray-200 rounded-full h-2">
                            <div class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                                 style="width: {{ $this->usagePercentage }}%"></div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Transaction History -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900">Historial de Transacciones</h3>
                <button wire:click="toggleTransactionHistory"
                        class="text-sm text-indigo-600 hover:text-indigo-500">
                    @if($showTransactionHistory)
                        Ocultar historial
                    @else
                        Ver historial completo
                    @endif
                </button>
            </div>
        </div>

        @if($showTransactionHistory)
            <div class="p-6">
                <!-- Period Filter -->
                <div class="mb-4">
                    <label for="period" class="block text-sm font-medium text-gray-700">Período</label>
                    <select wire:model.live="selectedPeriod" id="period"
                            class="mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm">
                        <option value="7">Últimos 7 días</option>
                        <option value="30">Últimos 30 días</option>
                        <option value="90">Últimos 90 días</option>
                        <option value="0">Todas las transacciones</option>
                    </select>
                </div>

                <!-- Transactions List -->
                @if($this->recentTransactions->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($this->recentTransactions as $transaction)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full {{ ($this->transactionTypeClass)($transaction['type']) }}">
                                            {{ ($this->transactionTypeIcon)($transaction['type']) }}
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ ($this->transactionTypeName)($transaction['type']) }}
                                        </p>
                                        <p class="text-sm text-gray-500 truncate">
                                            {{ $transaction['description'] }}
                                        </p>
                                        @if($transaction['upload_name'])
                                            <p class="text-xs text-gray-400">
                                                Archivo: {{ $transaction['upload_name'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm font-medium {{ $transaction['amount'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $transaction['amount'] > 0 ? '+' : '' }}{{ $transaction['amount'] }}
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        {{ $transaction['created_at']->format('d/m H:i') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="mt-6">
                        {{ $this->recentTransactions->links() }}
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No hay transacciones</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            No tienes transacciones en el período seleccionado.
                        </p>
                    </div>
                @endif
            </div>
        @else
            <!-- Recent Transactions Preview -->
            <div class="p-6">
                @php
                    $recentPreview = $this->recentTransactions->take(3);
                @endphp

                @if($recentPreview->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($recentPreview as $transaction)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full {{ ($this->transactionTypeClass)($transaction['type']) }}">
                                        {{ ($this->transactionTypeIcon)($transaction['type']) }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ ($this->transactionTypeName)($transaction['type']) }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $transaction['created_at']->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                </div>
                                <span class="text-sm font-medium {{ $transaction['amount'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $transaction['amount'] > 0 ? '+' : '' }}{{ $transaction['amount'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 text-center py-4">
                        No hay transacciones recientes.
                    </p>
                @endif
            </div>
        @endif
    </div>

    <!-- Warning for Low Credits -->
    @if($creditBalance <= 2)
        <div class="rounded-md bg-yellow-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">
                        Créditos Bajos
                    </h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>
                            Te quedan pocos créditos.
                            @if(!$this->subscriptionStatus)
                                <a href="{{ route('billing.subscriptions') }}" class="font-medium underline text-yellow-700 hover:text-yellow-600">
                                    Considera suscribirte a un plan
                                </a>
                                para obtener más créditos.
                            @else
                                Tus créditos se renovarán automáticamente con tu próxima facturación.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
