<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $categoryLabel }}</h1>
            <p class="text-gray-600 mt-1">Editar configuraciones de {{ strtolower($categoryLabel) }}</p>
        </div>
        <div class="flex space-x-3">
            <a
                href="{{ route('admin.email-settings.index') }}"
                wire:navigate
                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors"
            >
                <i class="fas fa-arrow-left mr-2"></i>Volver
            </a>
            <button
                wire:click="testCategorySettings"
                class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg font-medium transition-colors"
            >
                <i class="fas fa-flask mr-2"></i>Probar Categoría
            </button>
            <button
                wire:click="resetCategorySettings"
                wire:confirm="¿Estás seguro de que quieres restablecer esta categoría?"
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors"
            >
                <i class="fas fa-undo mr-2"></i>Restablecer
            </button>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            {{ session('error') }}
        </div>
    @endif

    @if (session()->has('info'))
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-6">
            {{ session('info') }}
        </div>
    @endif

    <!-- Settings Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <form wire:submit="updateSettings">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Configuraciones</h3>
                <p class="text-sm text-gray-500">Modifica las configuraciones según tus necesidades</p>
            </div>

            <div class="px-6 py-4 space-y-6">
                @foreach ($settings as $setting)
                    <div class="space-y-2">
                        <label for="{{ $setting['key'] }}" class="block text-sm font-medium text-gray-900">
                            {{ $setting['label'] }}
                            @if (!$setting['is_active'])
                                <span class="text-red-500 text-xs">(Inactivo)</span>
                            @endif
                        </label>

                        @if ($setting['description'])
                            <p class="text-xs text-gray-500">{{ $setting['description'] }}</p>
                        @endif

                        <div class="mt-1">
                            @if ($setting['type'] === 'boolean')
                                <div class="flex items-center space-x-3">
                                    <label class="flex items-center">
                                        <input
                                            type="radio"
                                            wire:model="formData.{{ $setting['key'] }}"
                                            value="1"
                                            class="text-green-600 focus:ring-green-500"
                                        >
                                        <span class="ml-2 text-sm text-gray-700">Habilitado</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input
                                            type="radio"
                                            wire:model="formData.{{ $setting['key'] }}"
                                            value="0"
                                            class="text-red-600 focus:ring-red-500"
                                        >
                                        <span class="ml-2 text-sm text-gray-700">Deshabilitado</span>
                                    </label>
                                </div>

                            @elseif ($setting['type'] === 'select' && isset($setting['options']))
                                <select
                                    wire:model="formData.{{ $setting['key'] }}"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                    @foreach (json_decode($setting['options'], true) as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>

                            @elseif ($setting['type'] === 'textarea')
                                <textarea
                                    wire:model="formData.{{ $setting['key'] }}"
                                    rows="3"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                ></textarea>

                            @elseif ($setting['type'] === 'integer')
                                <input
                                    type="number"
                                    wire:model="formData.{{ $setting['key'] }}"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >

                            @elseif ($setting['type'] === 'email')
                                <input
                                    type="email"
                                    wire:model="formData.{{ $setting['key'] }}"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >

                            @else
                                <input
                                    type="text"
                                    wire:model="formData.{{ $setting['key'] }}"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                            @endif
                        </div>

                        @error("formData.{$setting['key']}")
                            <p class="text-red-600 text-xs">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                    >
                        <span wire:loading.remove wire:target="updateSettings">
                            <i class="fas fa-save mr-2"></i>Guardar Cambios
                        </span>
                        <span wire:loading wire:target="updateSettings">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Guardando...
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Test Modal Component -->
    <livewire:admin.email-test-modal />
</div>
