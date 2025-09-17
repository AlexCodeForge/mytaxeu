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
    @livewireScriptConfig
    @stack('head')

    <!-- Alpine.js Global Store for Mobile Navigation -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('mobile', {
                open: false,
                toggle() {
                    this.open = !this.open;
                },
                close() {
                    this.open = false;
                },
                show() {
                    this.open = true;
                }
            });
        });
    </script>
</head>
<body class="min-h-full bg-gradient-to-br from-blue-50 via-white to-blue-100 text-gray-900 antialiased">
    <div class="flex h-screen">
        <!-- Sidebar (desktop) -->
        <aside class="hidden lg:block w-72 bg-white border-r">
            <livewire:panel.sidebar />
        </aside>

        <!-- Off-canvas for mobile -->
        <div class="lg:hidden" x-cloak>
            <div class="fixed inset-0 z-40"
                 x-show="$store.mobile.open"
                 x-transition:enter="transition-opacity ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @keydown.escape.window="$store.mobile.close()">
                <!-- Backdrop overlay with smooth fade -->
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm"
                     @click="$store.mobile.close()"
                     x-transition:enter="transition-opacity ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0">
                </div>

                <!-- Mobile sidebar with smooth slide animation -->
                <aside class="fixed inset-y-0 left-0 w-72 bg-white shadow-xl"
                       x-show="$store.mobile.open"
                       x-transition:enter="transition-transform ease-out duration-300"
                       x-transition:enter-start="transform -translate-x-full"
                       x-transition:enter-end="transform translate-x-0"
                       x-transition:leave="transition-transform ease-in duration-250"
                       x-transition:leave-start="transform translate-x-0"
                       x-transition:leave-end="transform -translate-x-full">
                    <livewire:panel.sidebar />
                </aside>
            </div>
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


