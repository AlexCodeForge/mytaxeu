@section('page_title', 'Subir CSV')

@push('styles')
<style>
    [x-cloak] { display: none !important; }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }

    .spinner-animate {
        animation: spin 1s linear infinite;
    }
</style>
@endpush

@push('scripts')
<script>
    function showToast(type, message, duration = 5000) {
        const toastId = 'toast-' + Date.now();
        const iconSuccess = `<svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z"/>
        </svg>`;
        const iconError = `<svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 11.793a1 1 0 1 1-1.414 1.414L10 11.414l-2.293 2.293a1 1 0 0 1-1.414-1.414L8.586 10 6.293 7.707a1 1 0 0 1 1.414-1.414L10 8.586l2.293-2.293a1 1 0 0 1 1.414 1.414L11.414 10l2.293 2.293Z"/>
        </svg>`;

        const bgColor = type === 'success' ? 'bg-green-100' : 'bg-red-100';
        const textColor = type === 'success' ? 'text-green-500' : 'text-red-500';
        const icon = type === 'success' ? iconSuccess : iconError;

        const toastHtml = `
            <div id="${toastId}" class="flex items-center w-full max-w-xs p-4 mb-4 text-gray-500 bg-white rounded-lg shadow" role="alert">
                <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 ${textColor} ${bgColor} rounded-lg">
                    ${icon}
                </div>
                <div class="ml-3 text-sm font-normal">${message}</div>
                <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-white text-gray-400 hover:text-gray-900 rounded-lg focus:ring-2 focus:ring-gray-300 p-1.5 hover:bg-gray-100 inline-flex items-center justify-center h-8 w-8" onclick="closeToast('${toastId}')" aria-label="Close">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                </button>
            </div>
        `;

        document.getElementById('toast-container').insertAdjacentHTML('beforeend', toastHtml);

        // Auto-close after duration
        setTimeout(() => closeToast(toastId), duration);
    }

    function closeToast(toastId) {
        const toast = document.getElementById(toastId);
        if (toast) {
            toast.remove();
        }
    }

    // Listen for Livewire events
    document.addEventListener('livewire:init', () => {
        console.log('🚀 Livewire initialized, setting up event listeners...');

        // Debug: Listen for all Livewire calls
        Livewire.hook('morph.updated', () => {
            console.log('🔍 Livewire component updated');
        });

        Livewire.hook('request', ({ uri, options, payload, respond, succeed, fail }) => {
            console.log('🌐 Livewire request:', { uri, payload });
        });

        // Try to catch all events
        Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
            console.log('📝 Livewire commit hook:', { commit });
            if (commit.effects && commit.effects.listeners) {
                console.log('🎧 Event listeners triggered:', commit.effects.listeners);
            }
        });

        Livewire.on('flash-message', (event) => {
            console.log('📨 Flash message event:', event);
            showToast(event.type, event.message);
        });

        Livewire.on('upload-success', (event) => {
            console.log('🎉 Upload success event received:', event);

            // Hide modal if it's open
            const modal = document.getElementById('period-confirmation-modal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.add('hidden');
                console.log('✅ Modal hidden after success');
            }

            showToast('success', event.message || 'Archivo subido exitosamente. El procesamiento ha comenzado.');

            console.log('🔄 Redirecting to uploads page in 2.5 seconds...');
            // Redirect after showing toast
            setTimeout(() => {
                console.log('🚀 Executing redirect to /uploads');
                window.location.href = '/uploads';
            }, 2500);
        });

        Livewire.on('upload-error', (event) => {
            console.log('Upload error event received:', event);
            showToast('error', event.message || 'Error al subir el archivo. Inténtalo de nuevo.');

            // Make sure modal stays visible if there was an error during confirmation
            const modal = document.getElementById('period-confirmation-modal');
            if (modal && modal.style.display === 'none') {
                console.log('Showing modal again due to error');
            }
        });

        Livewire.on('upload-info', (event) => {
            showToast('success', event.message, 8000); // Show for 8 seconds
        });

        Livewire.on('file-cancelled', () => {
            // Clear file selection when modal is cancelled
            const fileInput = document.getElementById('csvFile');
            if (fileInput) {
                fileInput.value = '';
            }
            // Update Alpine.js state
            window.dispatchEvent(new CustomEvent('file-cleared'));
        });

        // Modal is now controlled purely by Alpine.js x-show directive
        // No manual JavaScript control needed
    });
</script>
@endpush

@php
    $user = auth()->user();
    $limitValidator = app(\App\Services\UploadLimitValidator::class);
    $limitInfo = $user ? $limitValidator->getLimitInfo($user) : $limitValidator->getLimitInfo(null, request()->ip());
    $isAdmin = $limitInfo['is_admin'] ?? false;
    $currentLimit = $limitInfo['limit'];
    $userLimit = ($limitInfo['is_custom'] ?? false) ? $limitValidator->getUserLimit($user) : null;
    $isSubscription = $limitInfo['is_subscription'] ?? false;
    $hasUnlimitedAccess = $isAdmin || $isSubscription;
    $displayLimit = $hasUnlimitedAccess ? 'unlimited' : $currentLimit;
    $jsLimit = $hasUnlimitedAccess ? 999999999 : $currentLimit; // Use large number for JS comparison
@endphp

<div>
    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-5 right-5 z-50"></div>

    <div class="max-w-2xl mx-auto" x-data="{
    fileSelected: false,
    analyzing: false,
    lineCount: 0,
    fileName: '',
    hasUnlimitedAccess: {{ $hasUnlimitedAccess ? 'true' : 'false' }},
    init() {
        this.$watch('fileSelected', (value) => {
            if (!value) {
                this.lineCount = 0;
                this.fileName = '';
            }
        });

        // Watch for upload success
        this.$watch('$wire.uploadSuccess', (value) => {
            if (value) {
                console.log('🎉 Upload success detected, redirecting...');
                showToast('success', 'Archivo subido exitosamente. El procesamiento ha comenzado.');
                setTimeout(() => {
                    console.log('🚀 Redirecting to /uploads');
                    window.location.href = '/uploads';
                }, 2000);
            }
        });

        // Listen for file cancellation event
        window.addEventListener('file-cleared', () => {
            this.fileSelected = false;
            this.analyzing = false;
            this.lineCount = 0;
            this.fileName = '';
        });
    },
    async countLines(file) {
        if (!file) return 0;
        try {
            const text = await file.text();
            const lines = text.split('\n');
            // Filter out empty lines at the end
            let actualLines = lines.filter((line, index) => {
                if (index === lines.length - 1) {
                    return line.trim() !== '';
                }
                return true;
            });
            return Math.max(1, actualLines.length);
        } catch (error) {
            console.error('Error counting lines:', error);
            return 0;
        }
    }
}">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Subir Archivo CSV</h2>

        <!-- Current Limit Information -->

        <div class="mb-6 p-4 @if($hasUnlimitedAccess) bg-green-50 border-green-200 @else bg-blue-50 border-blue-200 @endif border rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    @if($isAdmin)
                        <h4 class="text-sm font-medium text-green-900">
                            Administrador: sin límites de líneas por archivo.
                        </h4>
                        <p class="text-xs text-green-700">Como administrador, puede procesar archivos CSV de cualquier tamaño.</p>
                    @elseif($isSubscription)
                        <h4 class="text-sm font-medium text-green-900">
                            Plan Premium: sin límites de líneas por archivo.
                        </h4>
                        <p class="text-xs text-green-700">Como suscriptor activo, puede procesar archivos CSV de cualquier tamaño.</p>
                    @elseif($userLimit)
                        <h4 class="text-sm font-medium text-blue-900">
                            Su límite actual es de {{ number_format($currentLimit) }} líneas por archivo.
                        </h4>
                        <p class="text-xs text-blue-700">
                            Límite personalizado: {{ number_format($currentLimit) }} líneas por archivo
                            @if($userLimit->expires_at)
                                - Este límite expira el {{ $userLimit->expires_at->format('d/m/Y') }}
                            @endif
                        </p>
                    @else
                        <h4 class="text-sm font-medium text-blue-900">
                            Su límite actual es de {{ number_format($currentLimit) }} líneas por archivo.
                        </h4>
                        <p class="text-xs text-blue-700">Plan gratuito: limitado a {{ number_format($currentLimit) }} líneas por archivo CSV.</p>
                    @endif
                </div>
                @if(!$hasUnlimitedAccess && !$userLimit)
                    <a href="{{ route('billing.subscriptions') }}" wire:navigate class="text-xs text-blue-600 hover:text-blue-800 underline">
                        Actualizar Plan
                    </a>
                @endif
            </div>
        </div>

        @if($uploading || $processingConfirmation || $uploadSuccess)
            <!-- Upload Progress -->
            <div class="mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="relative">
                            <div class="w-16 h-16 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="text-center">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            @if($uploadSuccess)
                                ¡Procesamiento exitoso!
                            @elseif($processingConfirmation)
                                Iniciando procesamiento
                            @else
                                Procesando archivo
                            @endif
                        </h3>
                        <p class="text-sm text-blue-700 font-medium mb-4">
                            @if($uploadSuccess)
                                Archivo subido exitosamente. Redirigiendo a la página de archivos...
                            @elseif($processingConfirmation)
                                Confirmando y preparando el archivo para procesamiento...
                            @else
                                {{ $uploadProgress }}
                            @endif
                        </p>
                        <div class="w-full bg-blue-200 rounded-full h-3">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-3 rounded-full @if($uploadSuccess) w-full bg-green-500 @else animate-pulse @endif"
                                 style="width: @if($uploadSuccess) 100% @else 75% @endif"></div>
                        </div>
                        <p class="text-xs text-gray-600 mt-3">
                            @if($uploadSuccess)
                                ¡Completado! Te redirigiremos en unos segundos...
                            @else
                                Por favor espera mientras procesamos tu archivo CSV...
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @else
            <!-- Upload Form -->
            <form wire:submit="processUpload" class="space-y-6">

                <!-- Flowbite Drag and Drop File Upload -->
                <div class="space-y-4">
                    <label for="csvFile" class="block text-sm font-medium text-gray-900 mb-2">
                        Seleccionar archivo CSV
                    </label>

                                        <!-- Drag and Drop Area -->
                    <div class="flex items-center justify-center w-full">
                        <label
                            for="csvFile"
                            class="flex flex-col items-center justify-center w-full h-64 border-2 border-dashed rounded-lg cursor-pointer transition-all duration-200 ease-in-out"
                            :class="{
                                'border-red-400 bg-red-50 hover:bg-red-100 hover:border-red-500': fileSelected && !hasUnlimitedAccess && lineCount > {{ $jsLimit }},
                                'border-green-400 bg-green-50 hover:bg-green-100 hover:border-green-500': fileSelected && (hasUnlimitedAccess || lineCount <= {{ $jsLimit }}),
                                'border-gray-300 bg-white hover:bg-gray-50 hover:border-gray-400': !fileSelected || analyzing
                            }"

                            x-on:livewire-upload-start="analyzing = true"
                            x-on:livewire-upload-finish="analyzing = false"
                            x-on:livewire-upload-cancel="analyzing = false; fileSelected = false"
                            x-on:livewire-upload-error="analyzing = false; fileSelected = false"
                        >
                            <!-- Hidden File Input -->
                            <input
                                type="file"
                                id="csvFile"
                                wire:model="csvFile"
                                accept=".csv"
                                x-ref="fileInput"
                                x-on:change="
                                    console.log('File input changed:', $event.target.files);
                                    fileSelected = $event.target.files.length > 0;
                                    if ($event.target.files.length > 0) {
                                        console.log('File selected:', $event.target.files[0].name);
                                    }
                                "
                                class="hidden"
                            >

                            <div class="flex flex-col items-center justify-center pt-5 pb-6" x-show="!fileSelected">
                                <!-- Upload Icon -->
                                <svg class="w-8 h-8 mb-4 text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.566 5.566 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                </svg>

                                <!-- Upload Text -->
                                <p class="mb-2 text-sm text-gray-600">
                                    <span class="font-semibold text-blue-600">Haz clic para subir</span> o arrastra y suelta
                                </p>
                                <p class="text-xs text-gray-500">CSV (Máx. 100MB)</p>
                            </div>

                            <!-- Analyzing State -->
                            <div x-show="analyzing && fileSelected" x-cloak class="flex flex-col items-center justify-center pt-8 pb-8">
                                <div class="text-center">
                                    <!-- Larger, more prominent spinner -->
                                    <div class="relative mb-6">
                                        <div class="w-16 h-16 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto"></div>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Analizando archivo...</h3>
                                    <p class="text-sm text-gray-600 mb-2">Verificando períodos y contenido del CSV</p>
                                    <div class="flex items-center justify-center text-xs text-gray-500">
                                        <div class="flex space-x-1">
                                            <div class="w-2 h-2 bg-blue-600 rounded-full animate-pulse"></div>
                                            <div class="w-2 h-2 bg-blue-600 rounded-full animate-pulse" style="animation-delay: 0.2s"></div>
                                            <div class="w-2 h-2 bg-blue-600 rounded-full animate-pulse" style="animation-delay: 0.4s"></div>
                                        </div>
                                        <span class="ml-2">Esto puede tomar unos segundos</span>
                                    </div>
                                </div>
                            </div>

                            <!-- File Selected State -->
                            <div x-show="fileSelected && !analyzing" x-cloak class="flex flex-col items-center justify-center pt-5 pb-6">
                                <!-- File Icon -->
                                <svg class="w-12 h-12 mb-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>

                                <!-- File Info -->
                                @if($csvFile)
                                    <p class="mb-2 text-sm font-medium text-gray-900">{{ $csvFile->getClientOriginalName() }}</p>
                                    <p class="text-sm mb-3 text-green-700 font-medium">Archivo cargado correctamente</p>
                                @else
                                    <p class="mb-2 text-sm font-medium text-gray-900">Archivo seleccionado</p>
                                @endif

                                <!-- Status Badge -->
                                <div class="flex items-center space-x-2">
                                    @if($csvFile)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                            ✓ Archivo listo
                                        </span>
                                    @endif

                                    <!-- Remove File Button -->
                                    <button
                                        type="button"
                                        x-on:click.stop="fileSelected = false; $wire.set('csvFile', null, false)"
                                        class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800 hover:bg-gray-200 transition-colors"
                                        title="Quitar archivo"
                                    >
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Quitar
                                    </button>
                                </div>
                            </div>
                        </label>
                    </div>



                    <!-- Format Info -->
                    <div class="text-sm text-gray-500">
        <p>Formatos permitidos: CSV • Tamaño máximo: 100MB</p>
                    </div>

                    <!-- Error Messages -->
                    @error('csvFile')
                        <div class="rounded-md bg-red-50 p-4">
                            <div class="flex"></div>
                                <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Error</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <p>{{ $message }}</p>
                                        @if(!$userLimit && str_contains($message, 'límite'))
                                            <div class="mt-2">
                                                <p>Para procesar archivos más grandes, considere actualizar a un plan premium.</p>
                                                <a href="{{ route('billing.subscriptions') }}" wire:navigate class="font-medium underline">
                                                    Ver planes premium →
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @enderror
                </div>

                <!-- File Info Preview -->
                @if($csvFile)
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-green-900 mb-2">Archivo seleccionado:</h4>
                        <div class="text-sm text-green-800">
                            <p><strong class="text-green-900">Nombre:</strong> {{ $csvFile->getClientOriginalName() }}</p>
                            <p><strong class="text-green-900">Tamaño:</strong> {{ number_format($csvFile->getSize() / 1024, 2) }} KB</p>
                            <p><strong class="text-green-900">Estado:</strong> <span class="text-green-700 font-medium">✓ Válido para procesar</span></p>
                        </div>
                    </div>
                @endif

                                <!-- Period Confirmation Modal -->
                @teleport('body')
                <div id="period-confirmation-modal"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
                     x-show="$wire.showPeriodConfirmation && !$wire.processingConfirmation && !$wire.uploadSuccess"
                     x-transition.opacity
                     @click.self="console.log('🔴 Modal backdrop clicked')"
                     x-effect="console.log('🔍 Modal visibility state:', {
                         showPeriodConfirmation: $wire.showPeriodConfirmation,
                         processingConfirmation: $wire.processingConfirmation,
                         uploadSuccess: $wire.uploadSuccess,
                         shouldShow: $wire.showPeriodConfirmation && !$wire.processingConfirmation && !$wire.uploadSuccess
                     })"
                >
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Confirmar Procesamiento</h3>
                            <button wire:click="cancelConfirmation" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="mb-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                                <h4 class="text-sm font-medium text-blue-900 mb-2">Análisis del Archivo:</h4>
                                <div class="text-sm text-blue-700 space-y-1">
                                    <p><strong>Archivo:</strong> {{ $csvFile?->getClientOriginalName() }}</p>
                                    <p><strong>Períodos detectados:</strong> {{ $periodAnalysis['period_count'] ?? 0 }}</p>
                                    @if(!empty($periodAnalysis['periods']))
                                        <div class="mt-2">
                                            <p><strong>Períodos encontrados:</strong></p>
                                            <ul class="list-disc list-inside ml-2 mt-1">
                                                @foreach($periodAnalysis['periods'] as $period)
                                                    <li>{{ $period }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                <h4 class="text-sm font-medium text-yellow-900 mb-3">Costo de Procesamiento:</h4>
                                <div class="text-sm text-yellow-700 space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span><strong>Créditos requeridos:</strong></span>
                                        <span class="font-bold text-yellow-900 text-lg">{{ $periodAnalysis['required_credits'] ?? 0 }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span><strong>Tus créditos actuales:</strong></span>
                                        <span class="font-bold text-lg {{ $userCredits >= ($periodAnalysis['required_credits'] ?? 0) ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $userCredits }}
                                        </span>
                                    </div>
                                    @if($userCredits >= ($periodAnalysis['required_credits'] ?? 0))
                                        <div class="border-t border-yellow-200 pt-3 mt-3">
                                            <div class="flex justify-between items-center">
                                                <span><strong>Créditos restantes:</strong></span>
                                                <span class="font-bold text-green-600 text-lg">
                                                    {{ $userCredits - ($periodAnalysis['required_credits'] ?? 0) }}
                                                </span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <div class="flex items-start text-sm text-blue-700">
                                    <svg class="w-4 h-4 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div class="leading-relaxed">
                                        <div class="font-medium">Se cobrará <strong>1 crédito por cada período</strong></div>
                                        <div class="text-xs text-blue-600 mt-1">(mes) de actividad detectado en tu archivo CSV.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex space-x-3">
                            <button
                                wire:click="cancelConfirmation"
                                class="flex-1 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                                :disabled="$wire.processingConfirmation"
                                :class="{ 'opacity-50 cursor-not-allowed': $wire.processingConfirmation }"
                            >
                                Cancelar
                            </button>
                            <button
                                wire:click="confirmUpload"
                                class="flex-1 px-4 py-2 text-white rounded-lg transition-colors flex items-center justify-center min-h-[40px]"
                                :class="{
                                    'bg-gray-400 cursor-not-allowed': $wire.processingConfirmation || {{ $userCredits < ($periodAnalysis['required_credits'] ?? 0) ? 'true' : 'false' }},
                                    'bg-primary hover:bg-blue-700': !$wire.processingConfirmation && {{ $userCredits >= ($periodAnalysis['required_credits'] ?? 0) ? 'true' : 'false' }}
                                }"
                                :disabled="$wire.processingConfirmation || {{ $userCredits < ($periodAnalysis['required_credits'] ?? 0) ? 'true' : 'false' }}"
                                wire:loading.attr="disabled"
                                wire:target="confirmUpload"
                            >
                                <!-- Loading Spinner -->
                                <svg wire:loading wire:target="confirmUpload" class="animate-spin h-5 w-5 text-white mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>

                                <!-- Button Text States -->
                                <span wire:loading wire:target="confirmUpload" class="text-white">Procesando...</span>
                                <span wire:loading.remove wire:target="confirmUpload">Confirmar y Procesar</span>
                            </button>
                        </div>
                    </div>
                </div>
                @endteleport

                <!-- Upload Instructions -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-blue-900 mb-2">Instrucciones:</h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Asegúrate de que tu archivo CSV contenga la columna 'ACTIVITY_PERIOD' requerida</li>
                        <li>• El costo es de <strong>1 crédito por cada período (mes)</strong> detectado en tu archivo</li>
                        <li>• Se permite un máximo de 3 períodos distintos por archivo</li>
                        @if(!$hasUnlimitedAccess && !$userLimit)
                        <li>• <strong>Usuarios sin créditos:</strong> máximo 100 líneas por mes</li>
                        @endif
                        <li>• Los archivos se procesarán automáticamente en segundo plano</li>
                        <li>• Recibirás notificaciones por email sobre el estado del procesamiento</li>
                    </ul>
                </div>

            </form>
        @endif
    </div>
</div>
