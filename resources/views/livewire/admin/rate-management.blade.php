<div class="space-y-6">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                Gestión de Tarifas
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Configurar las tarifas de cambio y VAT utilizadas por el transformador de CSV. Las tarifas son esenciales para el funcionamiento del sistema.
            </p>
        </div>
    </div>

    <!-- Success Message -->
    @if (session()->has('success'))
        <div class="rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L7.53 10.53a.75.75 0 00-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- General Errors -->
    @error('general')
        <div class="rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ $message }}</p>
                </div>
            </div>
        </div>
    @enderror

    <!-- API Status -->
    <div class="rounded-lg bg-white shadow">
        <div class="px-4 py-5 sm:p-6">
            <div class="sm:flex sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold leading-6 text-gray-900">Estado de la API</h3>
                    <div class="mt-2 max-w-xl text-sm text-gray-500">
                        <p>Conectividad con los servicios externos para actualizaciones automáticas de tarifas.</p>
                    </div>
                </div>
                <div class="mt-5 sm:ml-6 sm:mt-0 sm:flex sm:flex-shrink-0 sm:items-center">
                    <button type="button" wire:click="checkApiConnectivity"
                            wire:loading.attr="disabled"
                            wire:target="checkApiConnectivity"
                            class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50">
                        <svg wire:loading wire:target="checkApiConnectivity" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="checkApiConnectivity">Verificar Estado</span>
                        <span wire:loading wire:target="checkApiConnectivity">Verificando...</span>
                    </button>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-2 h-2 rounded-full mr-3 {{ isset($apiConnectivity['vatcomply_exchange_rates']) && $apiConnectivity['vatcomply_exchange_rates'] ? 'bg-green-400' : 'bg-red-400' }}"></div>
                        <span class="text-sm text-gray-700">VATComply - Tarifas de Cambio</span>
                    </div>
                    <span class="text-xs text-gray-500">
                        {{ isset($apiConnectivity['vatcomply_exchange_rates']) && $apiConnectivity['vatcomply_exchange_rates'] ? 'Conectado' : 'Desconectado' }}
                    </span>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-2 h-2 rounded-full mr-3 {{ isset($apiConnectivity['vatcomply_vat_validation']) && $apiConnectivity['vatcomply_vat_validation'] ? 'bg-green-400' : 'bg-red-400' }}"></div>
                        <span class="text-sm text-gray-700">VATComply - Validación VAT</span>
                    </div>
                    <span class="text-xs text-gray-500">
                        {{ isset($apiConnectivity['vatcomply_vat_validation']) && $apiConnectivity['vatcomply_vat_validation'] ? 'Conectado' : 'Desconectado' }}
                    </span>
                </div>
            </div>

            @if($lastApiCheck)
                <p class="mt-3 text-xs text-gray-500">Última verificación: {{ $lastApiCheck }}</p>
            @endif

            @if(isset($apiConnectivity['errors']) && !empty($apiConnectivity['errors']))
                <div class="mt-4 rounded-md bg-red-50 p-3">
                    <div class="text-sm text-red-700">
                        @foreach($apiConnectivity['errors'] as $error)
                            <p>• {{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="rounded-lg bg-white shadow">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex">
                <button wire:click="setTab('exchange')"
                        class="py-4 px-6 border-b-2 font-medium text-sm {{ $selectedTab === 'exchange' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                        </svg>
                        Tarifas de Cambio
                    </div>
                </button>
                <button wire:click="setTab('vat')"
                        class="py-4 px-6 border-b-2 font-medium text-sm {{ $selectedTab === 'vat' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        Tarifas VAT
                    </div>
                </button>
            </nav>
        </div>

        <!-- Content Area -->
        <div class="px-4 py-5 sm:p-6">
            <!-- Exchange Rates Tab -->
            @if($selectedTab === 'exchange')
                <div class="space-y-6">
                    <!-- API Update Section -->
                    <div class="rounded-md bg-blue-50 p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-blue-800">Actualización desde VATComply API</h4>
                                <p class="text-sm text-blue-600">Obtener las últimas tarifas de cambio desde VATComply (API gratuita)</p>
                                <p class="text-xs text-blue-500 mt-1">Actualiza 19 de 20 monedas. HRK requiere actualización manual.</p>
                            </div>
                            <button type="button" wire:click="updateFromApi('exchange')"
                                    wire:loading.attr="disabled"
                                    wire:target="updateFromApi"
                                    class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 disabled:opacity-50">
                                <svg wire:loading wire:target="updateFromApi" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="updateFromApi">Actualizar desde API</span>
                                <span wire:loading wire:target="updateFromApi">Actualizando...</span>
                            </button>
                        </div>
                    </div>

                    <!-- Exchange Rates Table -->
                    <div>
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Tarifas de Cambio Requeridas</h3>
                        <p class="text-sm text-gray-500 mb-4">Estas tarifas son esenciales para el funcionamiento del transformador CSV. No se pueden eliminar.</p>

                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Moneda
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tarifa (a EUR)
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Modo
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Última Actualización
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($exchangeRates as $rate)
                                        <tr class="hover:bg-gray-50">
                                            @if($editingExchangeRate === $rate->currency)
                                                <!-- Edit Mode Row -->
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <div class="flex items-center">
                                                        {{ $rate->currency }}
                                                        @if($rate->currency === 'HRK')
                                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                Solo Manual
                                                            </span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <input wire:model="editExchangeForm.rate"
                                                           type="number"
                                                           step="0.000001"
                                                           class="w-32 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                    @error('editExchangeForm.rate')
                                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <select wire:model="editExchangeForm.update_mode"
                                                            class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                        <option value="manual">Manual</option>
                                                        <option value="automatic">Automático</option>
                                                    </select>
                                                    @error('editExchangeForm.update_mode')
                                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $rate->updated_at->format('Y-m-d H:i') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                    <button type="button" wire:click="updateExchangeRate('{{ $rate->currency }}')"
                                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                        Guardar
                                                    </button>
                                                    <button type="button" wire:click="cancelEditExchangeRate"
                                                            class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        Cancelar
                                                    </button>
                                                </td>
                                            @else
                                                <!-- Display Mode Row -->
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <div class="flex items-center">
                                                        {{ $rate->currency }}
                                                        @if($rate->currency === 'HRK')
                                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                Solo Manual
                                                            </span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ number_format($rate->rate, 6) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $rate->update_mode === 'automatic' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                        {{ $rate->update_mode === 'automatic' ? 'Automático' : 'Manual' }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $rate->updated_at->format('Y-m-d H:i') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button type="button" wire:click="editExchangeRate('{{ $rate->currency }}')"
                                                            class="text-blue-600 hover:text-blue-900">
                                                        Editar
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- VAT Rates Tab -->
            @if($selectedTab === 'vat')
                <div class="space-y-6">
                    <!-- Info Section -->
                    <div class="rounded-md bg-yellow-50 p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h4 class="text-sm font-medium text-yellow-800">Tarifas VAT - Solo Manual</h4>
                                <p class="text-sm text-yellow-600">Las tarifas VAT no tienen API automática disponible. Se gestionan manualmente.</p>
                            </div>
                        </div>
                    </div>

                    <!-- VAT Rates Table -->
<div>
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Tarifas VAT de la UE Requeridas</h3>
                        <p class="text-sm text-gray-500 mb-4">Estas tarifas VAT son esenciales para el transformador CSV. No se pueden eliminar.</p>

                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            País
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tarifa VAT
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Modo
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Última Actualización
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Acciones
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($vatRates as $rate)
                                        <tr class="hover:bg-gray-50">
                                            @if($editingVatRate === $rate->country)
                                                <!-- Edit Mode Row -->
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $rate->country }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <input wire:model="editVatForm.rate"
                                                           type="number"
                                                           step="0.01"
                                                           min="0"
                                                           max="1"
                                                           placeholder="0.21"
                                                           class="w-24 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                    @error('editVatForm.rate')
                                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        Manual
                                                    </span>
                                                    <input type="hidden" wire:model="editVatForm.update_mode" value="manual">
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $rate->updated_at->format('Y-m-d H:i') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                    <button type="button" wire:click="updateVatRate('{{ $rate->country }}')"
                                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                        Guardar
                                                    </button>
                                                    <button type="button" wire:click="cancelEditVatRate"
                                                            class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        Cancelar
                                                    </button>
                                                </td>
                                            @else
                                                <!-- Display Mode Row -->
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $rate->country }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ number_format($rate->rate * 100, 2) }}%
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        Manual
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $rate->updated_at->format('Y-m-d H:i') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button type="button" wire:click="editVatRate('{{ $rate->country }}')"
                                                            class="text-blue-600 hover:text-blue-900">
                                                        Editar
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Command Reference removed -->
</div>
