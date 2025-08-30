@section('page_title', 'Subir CSV')

<div class="max-w-2xl mx-auto" x-data="csvUploadComponent()">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Subir Archivo CSV</h2>

        <!-- Current Limit Information -->
        @php
            $user = auth()->user();
            $limitValidator = app(\App\Services\UploadLimitValidator::class);
            $currentLimit = $user ? $limitValidator->getCurrentLimit($user) : 100;
            $userLimit = $user ? $limitValidator->getUserLimit($user) : null;
        @endphp
        
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-medium text-blue-900">
                        {{ __('csv_upload.current_limit_info', ['limit' => number_format($currentLimit)]) }}
                    </h4>
                    @if($userLimit)
                        <p class="text-xs text-blue-700">
                            {{ __('csv_upload.custom_limit_info', ['limit' => number_format($currentLimit)]) }}
                            @if($userLimit->expires_at)
                                - {{ __('csv_upload.limit_expires', ['date' => $userLimit->expires_at->format('d/m/Y')]) }}
                            @endif
                        </p>
                    @else
                        <p class="text-xs text-blue-700">{{ __('csv_upload.free_tier_info', ['limit' => number_format($currentLimit)]) }}</p>
                    @endif
                </div>
                @if(!$userLimit)
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
                <!-- File Input -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="csvFile" class="block text-sm font-medium text-gray-700">
                            Seleccionar archivo CSV
                        </label>
                        <!-- Line Count Indicator -->
                        <div class="text-sm font-medium" :class="lineCount > {{ $currentLimit }} ? 'text-red-600' : 'text-gray-600'">
                            <span x-text="lineCount"></span>/{{ number_format($currentLimit) }} líneas
                        </div>
                    </div>
                    
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-primary transition-colors"
                         :class="lineCount > {{ $currentLimit }} ? 'border-red-300 bg-red-50' : 'border-gray-300'">
                        <input
                            type="file"
                            id="csvFile"
                            wire:model="csvFile"
                            accept=".csv,.txt"
                            x-ref="fileInput"
                            @change="handleFileChange($event)"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-blue-700"
                        >
                        <p class="text-sm text-gray-600 mt-2">
                            Formatos permitidos: CSV, TXT. Tamaño máximo: 10MB
                        </p>
                        
                        <!-- Real-time Line Count Preview -->
                        <div x-show="fileSelected && !analyzing" class="mt-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center justify-center space-x-2 text-sm text-gray-600">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Analizando archivo...</span>
                            </div>
                        </div>
                        
                        <div x-show="fileSelected && analyzing && lineCount > 0" class="mt-3">
                            <div class="text-sm" :class="lineCount > {{ $currentLimit }} ? 'text-red-600' : 'text-green-600'">
                                <div x-show="lineCount <= {{ $currentLimit }}">
                                    ✓ {{ __('csv_upload.validation_passed', ['lines' => '']) }}<span x-text="lineCount"></span> líneas)
                                </div>
                                <div x-show="lineCount > {{ $currentLimit }}">
                                    ✗ El archivo excede el límite de {{ number_format($currentLimit) }} líneas
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @error('csvFile')
                        <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-red-600 text-sm">{{ $message }}</p>
                            @if(!$userLimit && str_contains($message, 'límite'))
                                <div class="mt-2 text-sm">
                                    <p class="text-gray-600">{{ __('csv_upload.upgrade_suggestion') }}</p>
                                    <a href="{{ route('billing.subscriptions') }}" wire:navigate class="text-blue-600 hover:text-blue-800 underline">
                                        Ver planes premium →
                                    </a>
                                </div>
                            @endif
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

                    <button
                        type="submit"
                        class="px-6 py-2 text-white rounded-lg transition-colors flex items-center"
                        :class="lineCount > {{ $currentLimit }} ? 'bg-gray-400 cursor-not-allowed' : 'bg-primary hover:bg-blue-700'"
                        :disabled="!csvFile || lineCount > {{ $currentLimit }}"
                        wire:loading.attr="disabled"
                        wire:target="upload"
                    >
                        <div wire:loading wire:target="upload" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                        <span wire:loading.remove wire:target="upload" x-text="lineCount > {{ $currentLimit }} ? 'Archivo excede límite' : 'Subir Archivo'"></span>
                        <span wire:loading wire:target="upload">Subiendo...</span>
                    </button>
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
        
        handleFileChange(event) {
            const file = event.target.files[0];
            if (!file) {
                this.fileSelected = false;
                this.lineCount = 0;
                return;
            }
            
            this.fileSelected = true;
            this.analyzing = false;
            this.countLinesInFile(file);
        },
        
        countLinesInFile(file) {
            if (!file.type.includes('text') && !file.name.endsWith('.csv') && !file.name.endsWith('.txt')) {
                this.lineCount = 0;
                return;
            }
            
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
                    
                    this.analyzing = true;
                } catch (error) {
                    console.error('Error analyzing CSV file:', error);
                    this.lineCount = 0;
                    this.analyzing = true;
                }
            };
            
            reader.onerror = () => {
                console.error('Error reading file');
                this.lineCount = 0;
                this.analyzing = true;
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
