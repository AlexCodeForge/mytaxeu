<div class="space-y-6">
    <!-- Usage Overview Cards -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Current Month Usage -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Uso Este Mes</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($currentMonthUsage) }}</p>
                        <p class="text-xs text-gray-500">líneas procesadas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Lines Processed -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Histórico</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalLinesProcessed) }}</p>
                        <p class="text-xs text-gray-500">líneas procesadas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Uploads -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Archivos Subidos</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalUploads) }}</p>
                        <p class="text-xs text-green-600">{{ $successfulUploads }} exitosos</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Credits Consumed -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Créditos Usados</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($totalCreditsConsumed) }}</p>
                        <p class="text-xs text-gray-500">total consumidos</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Progress and Limits -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Límite Mensual de Líneas</h3>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    {{ $usagePercentage >= 100 ? 'bg-red-100 text-red-800' :
                       ($usagePercentage >= 90 ? 'bg-yellow-100 text-yellow-800' :
                        ($usagePercentage >= 75 ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800')) }}">
                    {{ $this->getUsageStatusText() }}
                </span>
            </div>

            <div class="mb-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Progreso del mes</span>
                    <span class="text-gray-900">{{ number_format($currentMonthUsage) }} / {{ number_format($monthlyLimit) }}</span>
                </div>
                <div class="mt-2">
                    <div class="bg-gray-200 rounded-full h-3">
                        <div class="h-3 rounded-full transition-all duration-300 {{ $this->getUsageColorClass() }}"
                             style="width: {{ min($usagePercentage, 100) }}%"></div>
                    </div>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
                    <span>0</span>
                    <span class="font-medium">{{ number_format($usagePercentage, 1) }}% usado</span>
                    <span>{{ number_format($monthlyLimit) }}</span>
                </div>
            </div>

            @if($usagePercentage >= 90)
                <div class="rounded-md bg-{{ $usagePercentage >= 100 ? 'red' : 'yellow' }}-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-{{ $usagePercentage >= 100 ? 'red' : 'yellow' }}-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-{{ $usagePercentage >= 100 ? 'red' : 'yellow' }}-800">
                                @if($usagePercentage >= 100)
                                    Límite Excedido
                                @else
                                    Acercándote al Límite
                                @endif
                            </h3>
                            <div class="mt-2 text-sm text-{{ $usagePercentage >= 100 ? 'red' : 'yellow' }}-700">
                                <p>
                                    @if($usagePercentage >= 100)
                                        Has superado tu límite mensual de {{ number_format($monthlyLimit) }} líneas.
                                    @else
                                        Te quedan {{ number_format($remainingLimit) }} líneas para este mes.
                                    @endif
                                    <a href="{{ route('billing.subscriptions') }}" wire:navigate class="font-medium underline">
                                        Considera actualizar tu plan
                                    </a>
                                    para obtener más capacidad.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($totalUploads === 0)
        <!-- Empty State -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Sin datos de uso</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Aún no has procesado ningún archivo. Comienza subiendo tu primer archivo CSV.
                </p>
                <div class="mt-6">
                    <a href="{{ route('uploads.create') }}" wire:navigate
                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Subir Archivo
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
