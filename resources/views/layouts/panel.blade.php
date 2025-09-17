<!doctype html>
<html lang="es" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel — MyTaxEU')</title>
    <meta name="description" content="@yield('meta_description', 'Panel de administración y usuario de MyTaxEU')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- @livewireScriptConfig --}}
    @stack('head')

    <!-- Simplified Mobile Navigation Script -->
    <script>
        // Simple mobile menu toggle without Alpine store
        function toggleMobileMenu() {
            const mobileMenu = document.querySelector('[data-mobile-menu]');
            const backdrop = document.querySelector('[data-mobile-backdrop]');

            if (mobileMenu && backdrop) {
                const isHidden = mobileMenu.classList.contains('hidden');

                if (isHidden) {
                    // Show menu
                    mobileMenu.classList.remove('hidden');
                    backdrop.classList.remove('hidden');
                    setTimeout(() => {
                        mobileMenu.classList.remove('-translate-x-full');
                        backdrop.querySelector('div').classList.remove('opacity-0');
                    }, 10);
                } else {
                    // Hide menu
                    closeMobileMenu();
                }
            }
        }

        function closeMobileMenu() {
            const mobileMenu = document.querySelector('[data-mobile-menu]');
            const backdrop = document.querySelector('[data-mobile-backdrop]');

            if (mobileMenu && backdrop) {
                mobileMenu.classList.add('-translate-x-full');
                backdrop.querySelector('div').classList.add('opacity-0');

                setTimeout(() => {
                    mobileMenu.classList.add('hidden');
                    backdrop.classList.add('hidden');
                }, 300);
            }
        }

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });

        // Make functions globally available
        window.toggleMobileMenu = toggleMobileMenu;
        window.closeMobileMenu = closeMobileMenu;
    </script>
</head>
<body class="min-h-full bg-gradient-to-br from-blue-50 via-white to-blue-100 text-gray-900 antialiased">
    <div class="flex h-screen">
        <!-- Sidebar (desktop) -->
        <aside class="hidden lg:block bg-white border-r">
            <livewire:panel.sidebar />
        </aside>

        <!-- Off-canvas for mobile -->
        <div class="lg:hidden">
            <!-- Backdrop overlay -->
            <div class="fixed inset-0 z-40 hidden"
                 data-mobile-backdrop>
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm opacity-0 transition-opacity duration-300"
                     onclick="closeMobileMenu()">
                </div>
            </div>

            <!-- Mobile sidebar -->
            <aside class="fixed inset-y-0 left-0 w-72 bg-white shadow-xl z-50 transform -translate-x-full transition-transform duration-300 hidden"
                   data-mobile-menu>
                <livewire:panel.sidebar />
            </aside>
        </div>

        <!-- Main content -->
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="bg-white border-b">
                <livewire:panel.topbar />
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                @hasSection('content')
                    @yield('content')
                @else
                    {{ $slot }}
                @endif
            </main>
        </div>
    </div>

    @stack('scripts')
    @livewireScripts
</body>
</html>


