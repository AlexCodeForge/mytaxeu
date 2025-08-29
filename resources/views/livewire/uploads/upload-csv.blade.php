@section('page_title', 'Subir CSV')

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Subir Archivo CSV</h2>

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
                    <label for="csvFile" class="block text-sm font-medium text-gray-700 mb-2">
                        Seleccionar archivo CSV
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-primary transition-colors">
                        <input
                            type="file"
                            id="csvFile"
                            wire:model="csvFile"
                            accept=".csv,.txt"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-blue-700"
                        >
                        <p class="text-sm text-gray-600 mt-2">
                            Formatos permitidos: CSV, TXT. Tamaño máximo: 10MB
                        </p>
                    </div>
                    @error('csvFile')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
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
                        class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                        :disabled="!csvFile"
                        wire:loading.attr="disabled"
                        wire:target="upload"
                    >
                        <div wire:loading wire:target="upload" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                        <span wire:loading.remove wire:target="upload">Subir Archivo</span>
                        <span wire:loading wire:target="upload">Subiendo...</span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
