@section('page_title', 'Subir CSV')

@push('styles')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

<div class="max-w-2xl mx-auto" x-data="csvUploadComponent()">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Subir Archivo CSV</h2>

        <!-- Current Limit Information -->
        @php
            $user = auth()->user();
            $limitValidator = app(\App\Services\UploadLimitValidator::class);
            $isAdmin = $user && $user->isAdmin();
            $currentLimit = $user ? $limitValidator->getCurrentLimit($user) : 100;
            $userLimit = $user ? $limitValidator->getUserLimit($user) : null;
            $displayLimit = $isAdmin ? 'unlimited' : $currentLimit;
            $jsLimit = $isAdmin ? 999999999 : $currentLimit; // Use large number for JS comparison
        @endphp

        <div class="mb-6 p-4 @if($isAdmin) bg-green-50 border-green-200 @else bg-blue-50 border-blue-200 @endif border rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    @if($isAdmin)
                        <h4 class="text-sm font-medium text-green-900">
                            Administrador: sin límites de líneas por archivo.
                        </h4>
                        <p class="text-xs text-green-700">Como administrador, puede procesar archivos CSV de cualquier tamaño.</p>
                    @else
                        <h4 class="text-sm font-medium text-blue-900">
                            Su límite actual es de {{ number_format($currentLimit) }} líneas por archivo.
                        </h4>
                        @if($userLimit)
                            <p class="text-xs text-blue-700">
                                Límite personalizado: {{ number_format($currentLimit) }} líneas por archivo
                                @if($userLimit->expires_at)
                                    - Este límite expira el {{ $userLimit->expires_at->format('d/m/Y') }}
                                @endif
                            </p>
                        @else
                            <p class="text-xs text-blue-700">Plan gratuito: limitado a {{ number_format($currentLimit) }} líneas por archivo CSV.</p>
                        @endif
                    @endif
                </div>
                @if(!$isAdmin && !$userLimit)
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

                <!-- Flowbite File Upload Dropdown -->
                <div class="space-y-4">
                    <label for="csvFile" class="block text-sm font-medium text-gray-900 mb-2">
                        Seleccionar archivo CSV
                    </label>

                    <!-- File Input Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <!-- Hidden File Input -->
                        <input
                            type="file"
                            id="csvFile"
                            wire:model="csvFile"
                            accept=".csv,.txt"
                            x-ref="fileInput"
                            @change="handleFileChange($event)"
                            class="hidden"
                        >

                        <!-- Dropdown Button -->
                        <button
                            type="button"
                            @click="open = !open"
                            class="relative w-full cursor-pointer rounded-lg bg-white py-3 pl-3 pr-10 text-left text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 sm:text-sm sm:leading-6"
                            :class="{
                                'ring-red-500': fileSelected && !isAdmin && lineCount > {{ $jsLimit }},
                                'ring-green-500': fileSelected && (isAdmin || lineCount <= {{ $jsLimit }})
                            }"
                        >
                            <span class="flex items-center">
                                <!-- Icon -->
                                <svg class="h-5 w-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="ml-3 block truncate">
                                    <span x-show="!fileSelected && !analyzing">Selecciona un archivo CSV o TXT</span>
                                    <span x-show="analyzing" class="text-blue-600">Analizando archivo...</span>
                                    <span x-show="fileSelected && !analyzing" class="text-green-600" x-text="`Archivo seleccionado (${lineCount} líneas)`"></span>
                                </span>
                            </span>
                            <span class="pointer-events-none absolute inset-y-0 right-0 ml-3 flex items-center pr-2">
                                <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>

                        <!-- Dropdown Menu -->
                        <div
                            x-show="open"
                            x-cloak
                            @click.away="open = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
                        >
                            <button
                                type="button"
                                @click="$refs.fileInput.click(); open = false"
                                class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-gray-900 hover:bg-blue-600 hover:text-white w-full text-left"
                            >
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <span class="ml-3 block truncate font-normal">Buscar en mi dispositivo</span>
                                </div>
                            </button>

                            <div x-show="fileSelected" class="border-t border-gray-200">
                                <button
                                    type="button"
                                    @click="resetFile(); open = false"
                                    class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-gray-900 hover:bg-red-600 hover:text-white w-full text-left"
                                >
                                    <div class="flex items-center">
                                        <svg class="h-5 w-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        <span class="ml-3 block truncate font-normal">Quitar archivo</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- File Info Display -->
                    <div x-show="fileSelected && !analyzing" x-cloak class="rounded-lg bg-gray-50 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900" x-text="fileName"></p>
                                    <p class="text-sm text-gray-500">
                                        <span x-show="isAdmin" class="text-green-600">
                                            <span x-text="lineCount"></span> líneas (sin límite como admin)
                                        </span>
                                        <span x-show="!isAdmin" :class="lineCount > {{ $jsLimit }} ? 'text-red-600' : 'text-green-600'">
                                            <span x-text="lineCount"></span> de {{ number_format($currentLimit) }} líneas
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="text-sm">
                                <span x-show="isAdmin" class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    Admin - Sin límites
                                </span>
                                <span x-show="!isAdmin && lineCount <= {{ $jsLimit }}" class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                    ✓ Válido
                                </span>
                                <span x-show="!isAdmin && lineCount > {{ $jsLimit }}" class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                    ✗ Excede límite
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Processing State -->
                    <div x-show="analyzing" x-cloak class="rounded-lg bg-blue-50 p-4">
                        <div class="flex items-center">
                            <svg class="animate-spin h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 718-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-blue-900">Analizando archivo...</p>
                                <p class="text-sm text-blue-700">Contando líneas y validando formato</p>
                            </div>
                        </div>
                    </div>

                    <!-- Format Info -->
                    <div class="text-sm text-gray-500">
                        <p>Formatos permitidos: CSV, TXT • Tamaño máximo: 10MB</p>
                    </div>

                    <!-- Error Messages -->
                    @error('csvFile')
                        <div class="rounded-md bg-red-50 p-4">
                            <div class="flex">
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
        fileName: '',
        isAdmin: {{ $isAdmin ? 'true' : 'false' }},

        init() {
            // Ensure proper initialization
            this.fileSelected = false;
            this.analyzing = false;
            this.lineCount = 0;
            this.fileName = '';
        },

        handleFileChange(event) {
            const file = event.target.files[0];
            if (!file) {
                this.resetFile();
                return;
            }

            this.fileSelected = true;
            this.analyzing = false;
            this.fileName = file.name;
            this.countLinesInFile(file);
        },

        resetFile() {
            this.fileSelected = false;
            this.analyzing = false;
            this.lineCount = 0;
            this.fileName = '';
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
