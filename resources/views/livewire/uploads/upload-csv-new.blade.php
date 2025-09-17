@section('page_title', 'Subir CSV')

<div class="max-w-2xl mx-auto" x-data="csvUploadComponent()">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Subir Archivo CSV</h2>

        <!-- Current Limit Information -->
        @php
            $user = auth()->user();
            $limitValidator = app(\App\Services\UploadLimitValidator::class);
            $limitInfo = $user ? $limitValidator->getLimitInfo($user) : $limitValidator->getLimitInfo(null, request()->ip());
            $isAdmin = $limitInfo['is_admin'] ?? false;
            $currentLimit = $limitInfo['limit'];
            $userLimit = ($limitInfo['is_custom'] ?? false) ? $limitValidator->getUserLimit($user) : null;
            $isSubscription = $limitInfo['is_subscription'] ?? false;
            $hasCredits = $limitInfo['has_credits'] ?? false;
            $hasUnlimitedAccess = $isAdmin || $isSubscription || $hasCredits;
            $displayLimit = $hasUnlimitedAccess ? 'unlimited' : $currentLimit;
            $jsLimit = $hasUnlimitedAccess ? 999999999 : $currentLimit; // Use large number for JS comparison
        @endphp

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
                    @elseif($hasCredits)
                        <h4 class="text-sm font-medium text-green-900">
                            Con créditos: sin límites de líneas por archivo.
                        </h4>
                        <p class="text-xs text-green-700">Tienes créditos disponibles. Puedes procesar archivos CSV de cualquier tamaño mientras tengas créditos.</p>
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

        @if($uploading)
            <!-- Upload Progress -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-600">Progreso de subida</span>
                    <span class="text-sm text-gray-600">{{ $uploadProgress }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-primary h-2 rounded-full animate-pulse" style="width: 70%"></div>
                </div>
            </div>
        @else
            <!-- Upload Form -->
            <form wire:submit="upload" class="space-y-6">

                <!-- Beautiful Drag & Drop File Input -->
                <div class="space-y-4">
                    <!-- Drag and Drop Zone -->
                    <div
                        class="relative border-2 border-dashed border-gray-300 rounded-xl p-8 text-center transition-all duration-200 ease-in-out"
                        :class="{
                            'border-blue-500 bg-blue-50': dragOver,
                            'border-red-400 bg-red-50': fileSelected && !isAdmin && lineCount > {{ $jsLimit }},
                            'border-green-400 bg-green-50': fileSelected && (isAdmin || lineCount <= {{ $jsLimit }}),
                            'hover:border-blue-400 hover:bg-gray-50': !fileSelected && !dragOver
                        }"
                        @dragover.prevent="dragOver = true"
                        @dragleave="dragOver = false"
                        @drop.prevent="handleDrop($event)"
                        x-data="{ dragOver: false, isAdmin: {{ $isAdmin ? 'true' : 'false' }} }"
                    >
                        <!-- Hidden File Input -->
                        <input
                            type="file"
                            id="csvFile"
                            wire:model="csvFile"
                            accept=".csv,.txt"
                            x-ref="fileInput"
                            @change="handleFileChange($event)"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                        >

                        <!-- Upload Icon & Content -->
                        <div class="space-y-4">
                            <!-- Icon -->
                            <div class="mx-auto w-16 h-16 text-gray-400">
                                <svg x-show="!fileSelected && !analyzing" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>

                                <!-- File Icon When Selected -->
                                <svg x-show="fileSelected && !analyzing" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="text-green-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>

                                <!-- Spinner When Analyzing -->
                                <svg x-show="analyzing" class="animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>

                            <!-- Main Text -->
                            <div>
                                <!-- Default State -->
                                <div x-show="!fileSelected && !analyzing">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Arrastra tu archivo CSV aquí</h3>
                                    <p class="text-gray-600 mb-4">o haz clic para seleccionar desde tu dispositivo</p>
                                    <p class="text-sm text-gray-500">Formatos: CSV, TXT • Máximo: 10MB</p>
                                </div>

                                <!-- Analyzing State -->
                                <div x-show="analyzing">
                                    <h3 class="text-lg font-semibold text-blue-900 mb-2">Analizando archivo...</h3>
                                    <p class="text-blue-600">Contando líneas y validando formato</p>
                                </div>

                                <!-- File Selected State -->
                                <div x-show="fileSelected && !analyzing">
                                    <h3 class="text-lg font-semibold text-green-900 mb-2">¡Archivo listo!</h3>
                                    <div class="space-y-2">
                                        <!-- Line Count Info -->
                                        <div class="text-sm font-medium">
                                            @if($isAdmin)
                                                <span class="text-green-600">
                                                    <span x-text="lineCount"></span> líneas detectadas (sin límite como admin)
                                                </span>
                                            @else
                                                <span :class="lineCount > {{ $jsLimit }} ? 'text-red-600' : 'text-green-600'">
                                                    <span x-text="lineCount"></span> de {{ number_format($currentLimit) }} líneas
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Validation Status -->
                                        @if($isAdmin)
                                            <div class="text-sm text-green-600">
                                                ✓ Sin límites como administrador
                                            </div>
                                        @else
                                            <div x-show="lineCount <= {{ $jsLimit }}" class="text-sm text-green-600">
                                                ✓ El archivo pasa todas las validaciones
                                            </div>
                                            <div x-show="lineCount > {{ $jsLimit }}" class="text-sm text-red-600">
                                                ✗ El archivo excede el límite permitido
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Browse Button -->
                            <div x-show="!fileSelected">
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    @click="$refs.fileInput.click()"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                    Seleccionar archivo
                                </button>
                            </div>

                            <!-- Change File Button -->
                            <div x-show="fileSelected">
                                <button
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 border border-blue-300 rounded-md shadow-sm text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    @click="resetFile()"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Cambiar archivo
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Error Messages -->
                    @error('csvFile')
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex">
                                <svg class="w-5 h-5 text-red-400 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div>
                                    <p class="text-red-800 text-sm font-medium">{{ $message }}</p>
                                    @if(!$userLimit && str_contains($message, 'límite'))
                                        <div class="mt-2">
                                            <p class="text-red-700 text-sm">Para procesar archivos más grandes, considere actualizar a un plan premium.</p>
                                            <a href="{{ route('billing.subscriptions') }}" wire:navigate class="text-red-600 hover:text-red-800 underline text-sm font-medium">
                                                Ver planes premium →
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @enderror
                </div>

                <!-- File Info Preview -->
                @if($csvFile)
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-green-900 mb-2">Archivo seleccionado:</h4>
                        <div class="text-sm text-green-700">
                            <p><strong>Nombre:</strong> {{ $csvFile->getClientOriginalName() }}</p>
                            <p><strong>Tamaño:</strong> {{ number_format($csvFile->getSize() / 1024, 2) }} KB</p>
                            <p><strong>Tipo:</strong> {{ $csvFile->getMimeType() }}</p>
                        </div>
                    </div>
                @endif

                <!-- Upload Instructions -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-blue-900 mb-2">Instrucciones:</h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Asegúrate de que tu archivo CSV tenga la estructura correcta</li>
                        <li>• Los archivos se procesarán automáticamente en segundo plano</li>
                        <li>• Recibirás notificaciones por email sobre el estado del procesamiento</li>
                        <li>• Puedes ver el progreso en la sección "Mis Archivos"</li>
                    </ul>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-3">
                    @if($csvFile)
                        <button
                            type="button"
                            wire:click="cancelUpload"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            Cancelar
                        </button>
                    @endif

                    @if($isAdmin)
                        <button
                            type="submit"
                            class="px-6 py-2 text-white bg-primary hover:bg-blue-700 rounded-lg transition-colors flex items-center"
                            :disabled="!csvFile"
                            wire:loading.attr="disabled"
                            wire:target="upload"
                        >
                            <div wire:loading wire:target="upload" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                            <span wire:loading.remove wire:target="upload">Subir Archivo</span>
                            <span wire:loading wire:target="upload">Subiendo...</span>
                        </button>
                    @else
                        <button
                            type="submit"
                            class="px-6 py-2 text-white rounded-lg transition-colors flex items-center"
                            :class="lineCount > {{ $jsLimit }} ? 'bg-gray-400 cursor-not-allowed' : 'bg-primary hover:bg-blue-700'"
                            :disabled="!csvFile || lineCount > {{ $jsLimit }}"
                            wire:loading.attr="disabled"
                            wire:target="upload"
                        >
                            <div wire:loading wire:target="upload" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                            <span wire:loading.remove wire:target="upload" x-text="lineCount > {{ $jsLimit }} ? 'Archivo excede límite' : 'Subir Archivo'"></span>
                            <span wire:loading wire:target="upload">Subiendo...</span>
                        </button>
                    @endif
                </div>
            </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
function csvUploadComponent() {
    return {
        lineCount: 0,
        fileSelected: false,
        analyzing: false,

        init() {
            // Ensure proper initialization
            this.fileSelected = false;
            this.analyzing = false;
            this.lineCount = 0;
        },

        handleFileChange(event) {
            const file = event.target.files[0];
            if (!file) {
                this.resetFile();
                return;
            }

            this.fileSelected = true;
            this.analyzing = false;
            this.countLinesInFile(file);
        },

        handleDrop(event) {
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                // Check file type
                if (file.type.includes('text') || file.name.endsWith('.csv') || file.name.endsWith('.txt')) {
                    // Set the file to the input element
                    const input = this.$refs.fileInput;
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;

                    // Trigger change event
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
            this.dragOver = false;
        },

        resetFile() {
            this.fileSelected = false;
            this.analyzing = false;
            this.lineCount = 0;
            this.$refs.fileInput.value = '';
            // Trigger Livewire to clear the file
            this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        },

        countLinesInFile(file) {
            if (!file.type.includes('text') && !file.name.endsWith('.csv') && !file.name.endsWith('.txt')) {
                this.lineCount = 0;
                this.analyzing = false;
                return;
            }

            // Start analyzing
            this.analyzing = true;
            this.lineCount = 0;

            const reader = new FileReader();
            const sampleSize = Math.min(file.size, 50000); // Read first 50KB for preview

            reader.onload = (e) => {
                try {
                    const text = e.target.result;
                    const lines = text.split(/\r\n|\r|\n/);

                    // Filter out empty lines and estimate total
                    const nonEmptyLines = lines.filter(line => line.trim() !== '');

                    if (sampleSize < file.size) {
                        // Estimate total lines based on sample
                        const ratio = file.size / sampleSize;
                        this.lineCount = Math.ceil(nonEmptyLines.length * ratio);
                    } else {
                        this.lineCount = nonEmptyLines.length;
                    }

                    // Detect if first line is likely a header
                    if (this.lineCount > 0 && this.hasHeader(lines)) {
                        this.lineCount = Math.max(0, this.lineCount - 1);
                    }

                    // Done analyzing
                    this.analyzing = false;
                } catch (error) {
                    console.error('Error analyzing CSV file:', error);
                    this.lineCount = 0;
                    this.analyzing = false;
                }
            };

            reader.onerror = () => {
                console.error('Error reading file');
                this.lineCount = 0;
                this.analyzing = false;
            };

            // Read a sample of the file
            const blob = file.slice(0, sampleSize);
            reader.readAsText(blob);
        },

        hasHeader(lines) {
            if (lines.length < 2) return false;

            const firstLine = lines[0].split(/[,;|\t]/);
            const secondLine = lines[1].split(/[,;|\t]/);

            if (firstLine.length !== secondLine.length) return false;

            // Check if first line has non-numeric values and second has numeric
            let hasStringFirst = false;
            let hasNumericSecond = false;

            for (let i = 0; i < Math.min(firstLine.length, 3); i++) {
                const first = firstLine[i]?.trim().replace(/"/g, '');
                const second = secondLine[i]?.trim().replace(/"/g, '');

                if (first && isNaN(first) && !isNaN(second)) {
                    hasStringFirst = true;
                    hasNumericSecond = true;
                }
            }

            return hasStringFirst && hasNumericSecond;
        }
    }
}
</script>
@endpush
