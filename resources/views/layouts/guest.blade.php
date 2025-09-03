<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @livewireScriptConfig

        <style>
            .glass {
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .glass-dark {
                background: rgba(248, 250, 252, 0.95);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid rgba(203, 213, 225, 0.3);
            }

            .hero-bg {
                background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
            }

            .animate-float {
                animation: float 6s ease-in-out infinite;
            }

            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-20px); }
            }

            .text-primary { color: #1e40af; }
            .bg-primary { background-color: #1e40af; }

            .auth-form-width {
                width: 100%;
                max-width: 90vw;
            }

            @media (min-width: 640px) {
                .auth-form-width {
                    min-width: 442px;
                    max-width: 442px;
                }
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen hero-bg flex flex-col sm:justify-center items-center pt-6 sm:pt-0 relative overflow-hidden">
            <!-- Floating background elements -->
            <div class="absolute top-20 right-20 w-32 h-32 glass rounded-full animate-float opacity-30" style="animation-delay: -2s;"></div>
            <div class="absolute bottom-40 left-10 w-24 h-24 glass rounded-full animate-float opacity-20" style="animation-delay: -4s;"></div>
            <div class="absolute top-1/3 left-1/4 w-16 h-16 glass rounded-full animate-float opacity-25" style="animation-delay: -1s;"></div>

            <div class="relative z-10">
                <!-- Logo/Brand -->
                <div class="text-center mb-8">
                    <a href="/" wire:navigate class="inline-block">
                        <div class="text-4xl font-black text-white mb-2">MyTaxEU</div>
                        <p class="text-blue-100 text-sm">Automatiza la Gestión Fiscal de Amazon</p>
                    </a>
                </div>

                <!-- Main Card -->
                <div class="w-full glass-dark rounded-3xl p-8 shadow-2xl auth-form-width">
                    {{ $slot }}
                </div>

                <!-- Back to Home Link -->
                <div class="text-center mt-6">
                    <a href="/" wire:navigate class="text-blue-100 hover:text-white text-sm transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Volver al inicio
                    </a>
                </div>
            </div>
        </div>
    </body>
</html>
