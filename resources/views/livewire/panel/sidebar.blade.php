<div class="h-full max-h-screen grid grid-rows-[auto_1fr_auto] glass-white shadow-2xl transition-all duration-300 ease-in-out"
     x-data="{
         collapsed: JSON.parse(localStorage.getItem('sidebar-collapsed') || 'false'),
         animating: false,
         toggleCollapsed() {
             this.animating = true;
             this.collapsed = !this.collapsed;
             localStorage.setItem('sidebar-collapsed', JSON.stringify(this.collapsed));
             setTimeout(() => { this.animating = false; }, 300);
         }
     }"
     :class="{ 'w-20': collapsed, 'w-80': !collapsed }"
     style="overflow: hidden;"
     x-cloak>
    <!-- Header -->
    <div class="transition-all duration-300 ease-in-out border-b border-blue-200"
         :class="collapsed ? 'p-3' : 'p-6'">
        <!-- Expanded Header -->
        <div x-show="!collapsed"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="text-2xl font-bold text-primary whitespace-nowrap">MyTaxEU</div>
                @if (Auth::user()->isAdmin())
                <div class="ml-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full whitespace-nowrap">Admin</div>
                @endif
            </div>

            <div class="flex items-center space-x-2">
                <!-- Desktop collapse button -->
                <button class="hidden lg:inline-flex items-center justify-center w-9 h-9 rounded-lg bg-blue-50 hover:bg-blue-100 border border-blue-200 shadow-sm hover:shadow-md transition-all duration-200"
                        @click="toggleCollapsed()">
                    <svg class="w-4 h-4 text-blue-600 transition-transform duration-300"
                         :class="{ 'rotate-180': collapsed }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 19l-7-7 7-7M19 19l-7-7 7-7"></path>
                    </svg>
                </button>

                <!-- Mobile close button -->
                <button class="lg:hidden inline-flex items-center justify-center w-8 h-8 rounded-md hover:bg-gray-100 transition-colors duration-200"
                        onclick="closeMobileMenu()">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Collapsed Header -->
        <div x-show="collapsed"
             x-transition:enter="transition ease-out duration-300 delay-100"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="flex items-center justify-center w-full">
            <div class="text-xl font-bold text-primary">MT</div>
        </div>

        <!-- Collapsed expand button -->
        <div x-show="collapsed"
             x-transition:enter="transition ease-out duration-300 delay-150"
             x-transition:enter-start="opacity-0 transform translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform translate-y-2"
             class="mt-3 flex justify-center">
            <button class="hidden lg:inline-flex items-center justify-center w-10 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 border border-blue-200 shadow-sm hover:shadow-md transition-all duration-200"
                    @click="toggleCollapsed()">
                <svg class="w-4 h-4 text-blue-600 transition-transform duration-300"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 5l7 7-7 7M6 5l7 7-7 7"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Middle - Scrollable Navigation -->
    <nav class="min-h-0 relative transition-all duration-300 ease-in-out overflow-y-auto"
         :class="collapsed ? 'mt-3' : 'mt-6'"
         id="sidebar-nav"
         x-data="sidebarScroll()"
         x-init="initializeScroll()"
         @scroll="saveScrollPosition()">
        <div class="transition-all duration-300 ease-in-out"
             :class="collapsed ? 'px-1 pb-6' : 'px-4 pb-6'">
            <!-- Admin Section - Now appears first -->
            @if($isAdmin)
                <div class="mb-6">
                    <div x-show="!collapsed"
                         x-transition:enter="transition ease-out duration-300 delay-100"
                         x-transition:enter-start="opacity-0 transform translate-x-4"
                         x-transition:enter-end="opacity-100 transform translate-x-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 transform translate-x-0"
                         x-transition:leave-end="opacity-0 transform translate-x-4"
                         class="px-3 py-2 mb-3">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Administración</h3>
                    </div>
                    <ul class="space-y-2">
                        @foreach ($adminLinks as $link)
                            <li>
                                <a href="{{ route($link['route']) }}"
                                   id="nav-{{ $link['route'] }}"
                                   wire:navigate.hover
                                   wire:current.exact="bg-blue-100 text-blue-800 border-l-4 border-blue-500"
                                   class="group relative flex items-center text-gray-700 rounded-lg hover:bg-blue-50 sidebar-nav-link transition-all duration-200 ease-in-out {{ $this->isActiveRoute($link['route']) ? 'active-nav-item' : '' }}"
                                   :class="collapsed ? 'px-4 py-3 justify-center mx-1' : 'px-4 py-3'"
                                   :title="collapsed ? '{{ $link['label'] }}' : ''">
                                    <i class="fas {{ $link['icon'] }} transition-all duration-300 ease-in-out flex-shrink-0"
                                       :class="collapsed ? 'text-base' : 'mr-3 text-base'"></i>
                                    <span x-show="!collapsed"
                                          x-transition:enter="transition ease-out duration-300 delay-100"
                                          x-transition:enter-start="opacity-0 transform translate-x-4"
                                          x-transition:enter-end="opacity-100 transform translate-x-0"
                                          x-transition:leave="transition ease-in duration-200"
                                          x-transition:leave-start="opacity-100 transform translate-x-0"
                                          x-transition:leave-end="opacity-0 transform translate-x-4"
                                          class="whitespace-nowrap overflow-hidden">{{ $link['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- User Section -->
            <div class="{{ $isAdmin ? 'border-t border-gray-200 pt-6' : '' }} transition-all duration-300 ease-in-out">
                <div x-show="!collapsed"
                     x-transition:enter="transition ease-out duration-300 delay-100"
                     x-transition:enter-start="opacity-0 transform translate-x-4"
                     x-transition:enter-end="opacity-100 transform translate-x-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform translate-x-0"
                     x-transition:leave-end="opacity-0 transform translate-x-4"
                     class="px-3 py-2 mb-3">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Usuario</h3>
                </div>
                <ul class="space-y-2">
                    @foreach ($userLinks as $link)
                        <li>
                            <a href="{{ route($link['route']) }}"
                               id="nav-{{ $link['route'] }}"
                               wire:navigate.hover
                               wire:current.exact="bg-blue-100 text-blue-800 border-l-4 border-blue-500"
                               class="group relative flex items-center text-gray-700 rounded-lg hover:bg-blue-50 sidebar-nav-link transition-all duration-200 ease-in-out {{ $this->isActiveRoute($link['route']) ? 'active-nav-item' : '' }}"
                               :class="collapsed ? 'px-4 py-3 justify-center mx-1' : 'px-4 py-3'"
                               :title="collapsed ? '{{ $link['label'] }}' : ''">
                                <i class="fas {{ $link['icon'] }} transition-all duration-300 ease-in-out flex-shrink-0"
                                   :class="collapsed ? 'text-base' : 'mr-3 text-base'"></i>
                                <span x-show="!collapsed"
                                      x-transition:enter="transition ease-out duration-300 delay-100"
                                      x-transition:enter-start="opacity-0 transform translate-x-4"
                                      x-transition:enter-end="opacity-100 transform translate-x-0"
                                      x-transition:leave="transition ease-in duration-200"
                                      x-transition:leave-start="opacity-100 transform translate-x-0"
                                      x-transition:leave-end="opacity-0 transform translate-x-4"
                                      class="whitespace-nowrap overflow-hidden">{{ $link['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </nav>

    <!-- Footer -->
    <div class="border-t border-gray-200 transition-all duration-300 ease-in-out"
         :class="collapsed ? 'p-2' : 'p-4'">
        <div class="space-y-2">
            <!-- Logout Button -->
            <button wire:click="logout"
                    class="w-full flex items-center justify-center bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 hover:border-gray-300 transition-all duration-200 ease-in-out"
                    :class="collapsed ? 'px-4 py-3 mx-1' : 'px-4 py-2'"
                    :title="collapsed ? 'Cerrar Sesión' : ''">
                <i class="fas fa-sign-out-alt text-gray-600 transition-all duration-300 ease-in-out flex-shrink-0"
                   :class="collapsed ? 'text-base' : 'mr-2 text-base'"></i>
                <span x-show="!collapsed"
                      x-transition:enter="transition ease-out duration-300 delay-100"
                      x-transition:enter-start="opacity-0 transform translate-x-4"
                      x-transition:enter-end="opacity-100 transform translate-x-0"
                      x-transition:leave="transition ease-in duration-200"
                      x-transition:leave-start="opacity-100 transform translate-x-0"
                      x-transition:leave-end="opacity-0 transform translate-x-4"
                      class="whitespace-nowrap overflow-hidden">Cerrar Sesión</span>
            </button>
            <!-- Back to Site Button -->
            <a href="{{ route('landing') }}" wire:navigate
               class="w-full flex items-center justify-center bg-blue-50 text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-300 transition-all duration-200 ease-in-out"
               :class="collapsed ? 'px-4 py-3 mx-1' : 'px-4 py-2'"
               :title="collapsed ? 'Volver al Sitio' : ''">
                <i class="fas fa-arrow-left text-blue-600 transition-all duration-300 ease-in-out flex-shrink-0"
                   :class="collapsed ? 'text-base' : 'mr-2 text-base'"></i>
                <span x-show="!collapsed"
                      x-transition:enter="transition ease-out duration-300 delay-100"
                      x-transition:enter-start="opacity-0 transform translate-x-4"
                      x-transition:enter-end="opacity-100 transform translate-x-0"
                      x-transition:leave="transition ease-in duration-200"
                      x-transition:leave-start="opacity-100 transform translate-x-0"
                      x-transition:leave-end="opacity-0 transform translate-x-4"
                      class="whitespace-nowrap overflow-hidden">Volver al Sitio</span>
            </a>
        </div>
    </div>

    <!-- Embedded styles and scripts to maintain single root element for Livewire -->
    <style>
    /* Ensure smooth transitions and prevent layout shifts */
    [x-cloak] { display: none !important; }

    /* Sidebar smooth transitions */
    .sidebar-nav-link {
        transition-property: all;
        transition-timing-function: ease-in-out;
        transition-duration: 200ms;
    }

    .sidebar-nav-link:hover {
        transform: translateX(2px);
    }

    .sidebar-nav-link.active-nav-item {
        background-color: rgb(219 234 254);
        color: rgb(30 64 175);
        border-left: 4px solid rgb(59 130 246);
        transform: translateX(0);
    }

    /* Icon transitions */
    .fas {
        transition: all 0.3s ease-in-out;
    }

    /* Prevent text overflow during transitions */
    .whitespace-nowrap.overflow-hidden {
        text-overflow: ellipsis;
    }

    /* Smooth width transitions */
    .transition-all {
        transition-property: all;
        transition-timing-function: ease-in-out;
    }

    /* Ensure buttons maintain their shape during transitions */
    button, a {
        transition: all 0.2s ease-in-out;
    }

    /* Prevent content jumping during animations */
    .glass-white {
        will-change: width;
        transform-origin: left center;
    }
    </style>

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
</div>
