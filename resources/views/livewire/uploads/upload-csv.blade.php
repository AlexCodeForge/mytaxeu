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

                <!-- Flowbite Drag and Drop File Upload -->
                <div class="space-y-4">
                    <label for="csvFile" class="block text-sm font-medium text-gray-900 mb-2">
                        Seleccionar archivo CSV
                    </label>

                                        <!-- Drag and Drop Area -->
                    <div class="flex items-center justify-center w-full">
                        <label
                            for="csvFile"
                            class="flex flex-col items-center justify-center w-full h-64 border-2 border-dashed rounded-lg cursor-pointer transition-colors"
                            :class="{
                                'border-red-500 bg-red-50 dark:bg-red-900/20 dark:border-red-500': fileSelected && !isAdmin && lineCount > {{ $jsLimit }},
                                'border-green-500 bg-green-50 dark:bg-green-900/20 dark:border-green-500': fileSelected && (isAdmin || lineCount <= {{ $jsLimit }}),
                                'border-gray-300 bg-gray-50 hover:bg-gray-100 dark:hover:bg-gray-800 dark:bg-gray-700 dark:border-gray-600 dark:hover:border-gray-500': !fileSelected || analyzing
                            }"

                            x-on:livewire-upload-start="console.log('🚀 === LIVEWIRE UPLOAD START EVENT ==='); console.log('📅 Timestamp:', new Date().toISOString()); console.log('🔧 State before:', {analyzing: analyzing, fileSelected: fileSelected}); analyzing = true; fileSelected = false; console.log('🔧 State after:', {analyzing: analyzing, fileSelected: fileSelected});"
                            x-on:livewire-upload-finish="console.log('✅ === LIVEWIRE UPLOAD FINISH EVENT ==='); console.log('📅 Timestamp:', new Date().toISOString()); console.log('🔧 State before:', {analyzing: analyzing, fileSelected: fileSelected}); analyzing = false; fileSelected = true; fileName = $refs.fileInput?.files[0]?.name || 'Archivo subido'; console.log('📁 File name set to:', fileName); console.log('🔧 State after:', {analyzing: analyzing, fileSelected: fileSelected}); console.log('🚀 Calling countLinesFromLivewire...'); countLinesFromLivewire();"
                            x-on:livewire-upload-cancel="console.log('🚫 === LIVEWIRE UPLOAD CANCEL EVENT ==='); console.log('📅 Timestamp:', new Date().toISOString()); console.log('🔧 State before:', {analyzing: analyzing, fileSelected: fileSelected}); analyzing = false; fileSelected = false; console.log('🔧 State after:', {analyzing: analyzing, fileSelected: fileSelected});"
                            x-on:livewire-upload-error="console.log('❌ === LIVEWIRE UPLOAD ERROR EVENT ==='); console.log('📅 Timestamp:', new Date().toISOString()); console.log('🔧 State before:', {analyzing: analyzing, fileSelected: fileSelected}); analyzing = false; fileSelected = false; console.log('🔧 State after:', {analyzing: analyzing, fileSelected: fileSelected});"
                            x-on:livewire-upload-progress="console.log('📊 === LIVEWIRE UPLOAD PROGRESS EVENT ==='); console.log('📈 Progress:', $event.detail.progress + '%'); console.log('📅 Timestamp:', new Date().toISOString());"
                        >
                            <!-- Hidden File Input -->
                            <input
                                type="file"
                                id="csvFile"
                                wire:model="csvFile"
                                accept=".csv,.txt"
                                x-ref="fileInput"

                                x-on:livewire-upload-start="console.log('🔄 === FILE INPUT UPLOAD START ==='); console.log('📅 Input Timestamp:', new Date().toISOString());"
                                x-on:livewire-upload-finish="console.log('✅ === FILE INPUT UPLOAD FINISH ==='); console.log('📅 Input Timestamp:', new Date().toISOString());"
                                x-on:livewire-upload-cancel="console.log('🚫 === FILE INPUT UPLOAD CANCEL ==='); console.log('📅 Input Timestamp:', new Date().toISOString());"
                                x-on:livewire-upload-error="console.log('❌ === FILE INPUT UPLOAD ERROR ==='); console.log('📅 Input Timestamp:', new Date().toISOString());"
                                x-on:livewire-upload-progress="console.log('📊 === FILE INPUT UPLOAD PROGRESS ==='); console.log('📈 Input Progress:', $event.detail.progress + '%'); console.log('📅 Input Timestamp:', new Date().toISOString());"
                                x-on:change="console.log('📁 === FILE INPUT CHANGE EVENT ==='); console.log('📅 Change Timestamp:', new Date().toISOString()); console.log('📋 Event:', $event); console.log('📁 Files:', $event.target.files); if ($event.target.files.length > 0) { console.log('📄 Selected file:', $event.target.files[0]); }"
                                class="hidden"
                            >

                            <div class="flex flex-col items-center justify-center pt-5 pb-6" x-show="!fileSelected">
                                <!-- Upload Icon -->
                                <svg class="w-8 h-8 mb-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.566 5.566 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                </svg>

                                <!-- Upload Text -->
                                <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="font-semibold">Haz clic para subir</span> o arrastra y suelta
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">CSV o TXT (Máx. 10MB)</p>
                            </div>

                            <!-- Analyzing State -->
                            <div x-show="analyzing && fileSelected" x-cloak class="flex flex-col items-center justify-center pt-5 pb-6">
                                <div class="text-center">
                                    <div class="flex items-center justify-center mb-2">
                                        <svg class="animate-spin h-5 w-5 text-blue-500 mr-2" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 718-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 714 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-sm text-blue-600 font-medium">Analizando archivo...</span>
                                    </div>
                                    <p class="text-xs text-blue-500">Contando líneas y validando formato</p>
                                    <p class="text-xs text-gray-500 mt-1" x-text="fileName"></p>
                                </div>
                            </div>

                            <!-- File Selected State -->
                            <div x-show="fileSelected && !analyzing" x-cloak class="flex flex-col items-center justify-center pt-5 pb-6">
                                <!-- File Icon -->
                                <svg class="w-12 h-12 mb-4" :class="isAdmin || lineCount <= {{ $jsLimit }} ? 'text-green-500' : 'text-red-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>

                                <!-- File Info -->
                                <p class="mb-2 text-sm font-medium text-gray-900 dark:text-white" x-text="fileName"></p>
                                <p class="text-sm mb-3" :class="isAdmin || lineCount <= {{ $jsLimit }} ? 'text-green-600' : 'text-red-600'">
                                    <span x-show="isAdmin" x-text="`${lineCount} líneas (sin límite como admin)`"></span>
                                    <span x-show="!isAdmin" x-text="`${lineCount} de {{ number_format($currentLimit) }} líneas`"></span>
                                </p>

                                <!-- Status Badge -->
                                <div class="flex items-center space-x-2">
                                    <span x-show="isAdmin" class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                        ✓ Admin - Sin límites
                                    </span>
                                    <span x-show="!isAdmin && lineCount <= {{ $jsLimit }}" class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                        ✓ Válido
                                    </span>
                                    <span x-show="!isAdmin && lineCount > {{ $jsLimit }}" class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                        ✗ Excede límite
                                    </span>

                                    <!-- Remove File Button -->
                                    <button
                                        type="button"
                                        x-on:click.stop="resetFile()"
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
// 🚀 IMMEDIATE PAGE LOAD LOGGING
console.log('🌟 === UPLOAD CSV PAGE SCRIPT LOADING ===');
console.log('📅 Timestamp:', new Date().toISOString());
console.log('🔍 User Agent:', navigator.userAgent);
console.log('🌐 URL:', window.location.href);

// Test if Alpine.js is available
if (window.Alpine) {
    console.log('✅ Alpine.js detected');
} else {
    console.log('❌ Alpine.js NOT detected');
}

// Test if Livewire is available
if (window.Livewire) {
    console.log('✅ Livewire detected');
    console.log('🔧 Livewire object:', window.Livewire);

    // Add Livewire global event listeners
    window.Livewire.on('livewire:load', function () {
        console.log('🎯 === LIVEWIRE LOAD EVENT ===');
        console.log('📅 Timestamp:', new Date().toISOString());
    });

    window.Livewire.on('livewire:update', function () {
        console.log('🔄 === LIVEWIRE UPDATE EVENT ===');
        console.log('📅 Timestamp:', new Date().toISOString());
    });

} else {
    console.log('❌ Livewire NOT detected');
    console.log('🔍 Available global objects:', Object.keys(window).filter(key => key.includes('live') || key.includes('alpine')));
}

// Listen for DOM ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 === DOM CONTENT LOADED ===');
    console.log('📅 Timestamp:', new Date().toISOString());
});

// Listen for full page load
window.addEventListener('load', function() {
    console.log('🌐 === WINDOW LOAD COMPLETE ===');
    console.log('📅 Timestamp:', new Date().toISOString());
});

function csvUploadComponent() {
    console.log('🏗️ csvUploadComponent function called');

    return {
        lineCount: 0,
        fileSelected: false,
        analyzing: false,
        fileName: '',
        isAdmin: {{ $isAdmin ? 'true' : 'false' }},

        // Watchers for property changes
        $watch: {
            lineCount(newValue, oldValue) {
                console.log('📊 lineCount changed:', {
                    from: oldValue,
                    to: newValue,
                    timestamp: new Date().toISOString()
                });
            },

            fileSelected(newValue, oldValue) {
                console.log('📁 fileSelected changed:', {
                    from: oldValue,
                    to: newValue,
                    timestamp: new Date().toISOString()
                });
            },

            analyzing(newValue, oldValue) {
                console.log('🔍 analyzing changed:', {
                    from: oldValue,
                    to: newValue,
                    timestamp: new Date().toISOString()
                });
            },

            fileName(newValue, oldValue) {
                console.log('📄 fileName changed:', {
                    from: oldValue,
                    to: newValue,
                    timestamp: new Date().toISOString()
                });
            }
        },

        init() {
            console.log('🚀 === CSV UPLOAD COMPONENT INIT START ===');
            console.log('📅 Init timestamp:', new Date().toISOString());
            console.log('👤 Is Admin:', this.isAdmin);
            console.log('🔧 Initial state:', {
                fileSelected: this.fileSelected,
                analyzing: this.analyzing,
                lineCount: this.lineCount,
                fileName: this.fileName
            });

            // Ensure proper initialization
            this.fileSelected = false;
            this.analyzing = false;
            this.lineCount = 0;
            this.fileName = '';

            console.log('🧹 State after reset:', {
                fileSelected: this.fileSelected,
                analyzing: this.analyzing,
                lineCount: this.lineCount,
                fileName: this.fileName
            });

            // Clear any cached file input value on page load
            this.$nextTick(() => {
                console.log('⏭️ $nextTick callback executing');
                if (this.$refs.fileInput) {
                    this.$refs.fileInput.value = '';
                    console.log('✅ File input cleared on initialization');
                    console.log('📋 File input element:', this.$refs.fileInput);
                } else {
                    console.log('❌ File input element NOT found in $refs');
                    console.log('🔍 Available refs:', Object.keys(this.$refs || {}));
                }
            });

            console.log('✅ === CSV UPLOAD COMPONENT INIT COMPLETE ===');
        },

        // Called when Livewire upload finishes to analyze the uploaded file
        countLinesFromLivewire() {
            console.log('🔍 === COUNT LINES FROM LIVEWIRE START ===');
            console.log('📅 Timestamp:', new Date().toISOString());
            console.log('🔧 Current state:', {
                fileSelected: this.fileSelected,
                analyzing: this.analyzing,
                lineCount: this.lineCount,
                fileName: this.fileName
            });

            // Get the file from the input element
            const file = this.$refs.fileInput?.files[0];
            console.log('🔍 Checking for file in input element');
            console.log('📋 File input element:', this.$refs.fileInput);
            console.log('📁 Files array:', this.$refs.fileInput?.files);
            console.log('📄 Selected file:', file);

            if (file) {
                console.log('✅ Found file from input:', {
                    name: file.name,
                    size: file.size,
                    type: file.type,
                    lastModified: file.lastModified
                });
                console.log('🚀 Starting line count analysis...');
                this.countLinesInFile(file);
            } else {
                console.log('❌ No file found in input after Livewire upload');
                console.log('🔍 Input element details:', {
                    hasInput: !!this.$refs.fileInput,
                    filesLength: this.$refs.fileInput?.files?.length || 0,
                    value: this.$refs.fileInput?.value || 'empty'
                });
                // Fallback - just mark as selected without line count for now
                this.fileSelected = true;
                this.lineCount = 0;
                console.log('📝 Set fallback state: fileSelected=true, lineCount=0');
            }
            console.log('✅ === COUNT LINES FROM LIVEWIRE END ===');
        },

        isValidFileType(file) {
            const validTypes = ['text/csv', 'text/plain', 'application/csv'];
            const validExtensions = ['.csv', '.txt'];

            const isValid = validTypes.includes(file.type) ||
                           validExtensions.some(ext => file.name.toLowerCase().endsWith(ext));

            console.log('🔍 File type validation:', {
                fileName: file.name,
                fileType: file.type,
                isValid: isValid,
                validTypes: validTypes,
                validExtensions: validExtensions
            });

            return isValid;
        },

        resetFile() {
            console.log('🔄 Resetting file state');
            this.fileSelected = false;
            this.analyzing = false;
            this.lineCount = 0;
            this.fileName = '';
            this.$refs.fileInput.value = '';
            // Trigger Livewire to clear the file
            this.$refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            console.log('✅ File state reset completed');
        },

        countLinesInFile(file) {
            console.log('📊 Starting line count for file:', file.name);

            if (!file.type.includes('text') && !file.name.endsWith('.csv') && !file.name.endsWith('.txt')) {
                console.log('❌ File type not supported for line counting');
                this.lineCount = 0;
                this.analyzing = false;
                return;
            }

            // Start analyzing
            console.log('🔄 Setting analyzing state to true');
            this.analyzing = true;
            this.lineCount = 0;

            const reader = new FileReader();
            const sampleSize = Math.min(file.size, 50000); // Read first 50KB for preview

            console.log('📖 FileReader setup:', {
                fileSize: file.size,
                sampleSize: sampleSize,
                readingFullFile: sampleSize >= file.size
            });

            reader.onload = (e) => {
                console.log('📚 File reading completed, processing content...');
                try {
                    const text = e.target.result;
                    const lines = text.split(/\r\n|\r|\n/);
                    console.log('📄 Raw lines found:', lines.length);

                    // Filter out empty lines and estimate total
                    const nonEmptyLines = lines.filter(line => line.trim() !== '');
                    console.log('📝 Non-empty lines:', nonEmptyLines.length);

                    if (sampleSize < file.size) {
                        // Estimate total lines based on sample
                        const ratio = file.size / sampleSize;
                        this.lineCount = Math.ceil(nonEmptyLines.length * ratio);
                        console.log('🔢 Estimated total lines (based on sample):', this.lineCount, 'ratio:', ratio);
                    } else {
                        this.lineCount = nonEmptyLines.length;
                        console.log('🔢 Actual total lines (full file read):', this.lineCount);
                    }

                    // Detect if first line is likely a header
                    const hasHeaderDetected = this.hasHeader(lines);
                    console.log('🏷️ Header detection:', hasHeaderDetected);

                    if (this.lineCount > 0 && hasHeaderDetected) {
                        this.lineCount = Math.max(0, this.lineCount - 1);
                        console.log('🔢 Adjusted line count (removed header):', this.lineCount);
                    }

                    // Done analyzing
                    console.log('✅ Analysis complete. Final line count:', this.lineCount);
                    this.analyzing = false;

                    console.log('📊 Final state after analysis:', {
                        fileSelected: this.fileSelected,
                        analyzing: this.analyzing,
                        fileName: this.fileName,
                        lineCount: this.lineCount,
                        isAdmin: this.isAdmin
                    });

                } catch (error) {
                    console.error('❌ Error analyzing CSV file:', error);
                    this.lineCount = 0;
                    this.analyzing = false;
                }
            };

            reader.onerror = (error) => {
                console.error('❌ Error reading file:', error);
                this.lineCount = 0;
                this.analyzing = false;
            };

            // Read a sample of the file
            console.log('🚀 Starting file read...');
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
