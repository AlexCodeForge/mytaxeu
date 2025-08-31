<!doctype html>
<html lang="es" class="h-full scroll-smooth" x-data="{ mobileOpen: false }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel — MyTaxEU')</title>
    <meta name="description" content="@yield('meta_description', 'Panel de administración y usuario de MyTaxEU')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @livewireScriptConfig
    @stack('head')
</head>
<body class="min-h-full bg-gradient-to-br from-blue-50 via-white to-blue-100 text-gray-900 antialiased">
    <div class="flex h-screen">
        <!-- Sidebar (desktop) -->
        <aside class="hidden lg:block w-72 bg-white border-r">
            <livewire:panel.sidebar />
        </aside>

        <!-- Off-canvas for mobile -->
        <div class="lg:hidden" x-cloak>
            <div class="fixed inset-0 z-40" x-show="mobileOpen" @keydown.escape.window="mobileOpen=false">
                <div class="fixed inset-0 bg-black/40" @click="mobileOpen=false"></div>
                <aside class="fixed inset-y-0 left-0 w-72 bg-white shadow-xl" x-show="mobileOpen" x-transition>
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
                {{ $slot }}
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>


