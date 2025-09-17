<div class="h-full max-h-screen grid grid-rows-[auto_1fr_auto] glass-white shadow-2xl overflow-hidden">
    <!-- Header -->
    <div class="p-6 border-b border-blue-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="text-2xl font-bold text-primary">MyTaxEU</div>
                @if (Auth::user()->isAdmin())
                <div class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Admin</div>
                @endif
            </div>
            <!-- Mobile close button -->
            <button class="lg:hidden inline-flex items-center justify-center w-8 h-8 rounded-md hover:bg-gray-100 transition-colors duration-200"
                    @click="$store.mobile.close()">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Middle - Scrollable Navigation -->
    <nav class="overflow-y-auto mt-6 min-h-0"
         id="sidebar-nav"
         x-data="sidebarScroll()"
         x-init="initializeScroll()"
         @scroll="saveScrollPosition()">
        <div class="px-4 pb-6">
            <!-- Admin Section - Now appears first -->
            @if($isAdmin)
                <div class="mb-6">
                    <div class="px-3 py-2 mb-3">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Administración</h3>
                    </div>
                    <ul class="space-y-2">
                        @foreach ($adminLinks as $link)
                            <li>
                                <a href="{{ route($link['route']) }}"
                                   id="nav-{{ $link['route'] }}"
                                   wire:navigate.hover
                                   wire:current.exact="bg-blue-100 text-blue-800 border-l-4 border-blue-500"
                                   class="flex items-center px-4 py-3 text-gray-700 rounded-lg transition-all hover:bg-blue-50 sidebar-nav-link {{ $this->isActiveRoute($link['route']) ? 'active-nav-item' : '' }}">
                                    <i class="fas {{ $link['icon'] }} mr-3"></i>
                                    <span>{{ $link['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- User Section -->
            <div class="{{ $isAdmin ? 'border-t border-gray-200 pt-6' : '' }}">
                <div class="px-3 py-2 mb-3">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Usuario</h3>
                </div>
                <ul class="space-y-2">
                    @foreach ($userLinks as $link)
                        <li>
                            <a href="{{ route($link['route']) }}"
                               id="nav-{{ $link['route'] }}"
                               wire:navigate.hover
                               wire:current.exact="bg-blue-100 text-blue-800 border-l-4 border-blue-500"
                               class="flex items-center px-4 py-3 text-gray-700 rounded-lg transition-all hover:bg-blue-50 sidebar-nav-link {{ $this->isActiveRoute($link['route']) ? 'active-nav-item' : '' }}">
                                <i class="fas {{ $link['icon'] }} mr-3"></i>
                                <span>{{ $link['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </nav>

    <!-- Footer -->
    <div class="p-4 border-t border-gray-200">
        <div class="space-y-2">
            <!-- Logout Button -->
            <button wire:click="logout" class="w-full flex items-center justify-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all">
                <i class="fas fa-sign-out-alt mr-2"></i>
                Cerrar Sesión
            </button>
            <!-- Back to Site Button -->
            <a href="{{ route('landing') }}" wire:navigate class="w-full flex items-center justify-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 transition-all">
                <i class="fas fa-arrow-left mr-2"></i>
                Volver al Sitio
            </a>
        </div>
    </div>
</div>

<script>
function sidebarScroll() {
    return {
        scrollPosition: 0,

        initializeScroll() {
            // Restore scroll position on page load
            const savedPosition = sessionStorage.getItem('sidebar-scroll-position');
            if (savedPosition) {
                this.$el.scrollTop = parseInt(savedPosition);
            }

            // Auto-scroll to active item if it exists and no saved position
            if (!savedPosition) {
                this.scrollToActiveItem();
            }

            // Listen for Livewire navigation events
            document.addEventListener('livewire:navigated', () => {
                this.scrollToActiveItem();
            });
        },

        saveScrollPosition() {
            sessionStorage.setItem('sidebar-scroll-position', this.$el.scrollTop);
        },

        scrollToActiveItem() {
            const activeItem = this.$el.querySelector('.active-nav-item');
            if (activeItem) {
                // Small delay to ensure DOM is ready
                setTimeout(() => {
                    const nav = this.$el;
                    const navRect = nav.getBoundingClientRect();
                    const itemRect = activeItem.getBoundingClientRect();

                    // Calculate if item is visible
                    const isVisible = itemRect.top >= navRect.top && itemRect.bottom <= navRect.bottom;

                    if (!isVisible) {
                        // Scroll to center the active item in the nav area
                        const scrollTop = activeItem.offsetTop - nav.offsetTop - (nav.clientHeight / 2) + (activeItem.clientHeight / 2);
                        nav.scrollTo({
                            top: Math.max(0, scrollTop),
                            behavior: 'smooth'
                        });
                    }
                }, 100);
            }
        }
    }
}
</script>


