@if ($paginator->hasPages())
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <!-- Info and Per Page Selector -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="text-sm text-gray-700">
                Mostrando
                <span class="font-medium">{{ $paginator->firstItem() }}</span>
                a
                <span class="font-medium">{{ $paginator->lastItem() }}</span>
                de
                <span class="font-medium">{{ $paginator->total() }}</span>
                resultados
            </div>

            <!-- Per Page Selector -->
            <div class="flex items-center gap-2">
                <label for="perPage" class="text-sm text-gray-700 whitespace-nowrap">Por página:</label>
                <select wire:model.live="perPage" id="perPage" class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <!-- Pagination Controls -->
        <nav role="navigation" aria-label="Pagination Navigation">
            <div class="flex items-center gap-1">
                {{-- Previous Page Link --}}
                @if ($paginator->onFirstPage())
                    <span class="inline-flex items-center justify-center w-8 h-8 text-gray-400 bg-white border border-gray-300 rounded-lg cursor-not-allowed">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    </span>
                @else
                    <button
                        wire:click="previousPage"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center w-8 h-8 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 transition-colors"
                        aria-label="Página anterior"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                @endif

                {{-- Pagination Elements --}}
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span class="inline-flex items-center justify-center w-8 h-8 text-gray-700 bg-white border border-gray-300 rounded-lg">
                            {{ $element }}
                        </span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="inline-flex items-center justify-center w-8 h-8 text-white bg-blue-600 border border-blue-600 rounded-lg font-medium">
                                    {{ $page }}
                                </span>
                            @else
                                <button
                                    wire:click="gotoPage({{ $page }})"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center justify-center w-8 h-8 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 transition-colors"
                                    aria-label="Ir a la página {{ $page }}"
                                >
                                    {{ $page }}
                                </button>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ($paginator->hasMorePages())
                    <button
                        wire:click="nextPage"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center w-8 h-8 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 transition-colors"
                        aria-label="Página siguiente"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                @else
                    <span class="inline-flex items-center justify-center w-8 h-8 text-gray-400 bg-white border border-gray-300 rounded-lg cursor-not-allowed">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    </span>
                @endif
            </div>
        </nav>
    </div>
@endif
