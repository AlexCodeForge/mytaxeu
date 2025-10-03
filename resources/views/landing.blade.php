@extends('layouts.marketing')

@section('title', 'MyTaxEU - Automatiza la Gestión Fiscal de Amazon')
@push('head')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    @vite(['resources/css/landing.css'])
@endpush

@section('body')
<div class="bg-gradient-to-br from-blue-50 via-white to-blue-100 min-h-screen" x-data="{
    observeIntersection() {
        return {
            fadeIn: false,
            init() {
                this.$el.classList.add('opacity-0', 'translate-y-8', 'transition-all', 'duration-700');
            }
        }
    }
}">
    <!-- Navigation -->
    <nav class="fixed top-0 w-full z-50 bg-white/95 backdrop-blur-md shadow-lg border-b border-blue-100" x-data="{ mobileMenuOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0">
                    <div class="text-2xl font-bold text-primary">MyTaxEU</div>
                </div>

                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-8">
                        <a href="#inicio" class="text-gray-800 hover:text-primary transition-colors font-medium">Inicio</a>
                        <a href="#beneficios" class="text-gray-800 hover:text-primary transition-colors font-medium">Beneficios</a>
                        <a href="#precios" class="text-gray-800 hover:text-primary transition-colors font-medium">Precios</a>
                        <a href="#faq" class="text-gray-800 hover:text-primary transition-colors font-medium">FAQ</a>
                    </div>
                </div>

                <div class="flex items-center space-x-4 hidden md:flex">
                    @auth
                        <a href="{{ route('dashboard') }}" class="bg-blue-50 hover:bg-blue-100 px-4 py-2 rounded-lg text-primary font-medium transition-all">
                            <i class="fas fa-cog mr-2"></i>Admin
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="bg-blue-50 hover:bg-blue-100 px-4 py-2 rounded-lg text-primary font-medium transition-all">
                            <i class="fas fa-sign-in-alt mr-2"></i>Ingresa
                        </a>
                    @endauth
                    <a href="{{ route('register') }}" class="bg-primary text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-all transform hover:scale-105 shadow-md">
                        Empezar Gratis
                    </a>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-800 hover:text-primary focus:outline-none focus:text-primary">
                        <i class="fas text-xl" :class="mobileMenuOpen ? 'fa-times' : 'fa-bars'"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation Menu -->
            <div x-show="mobileMenuOpen" x-transition class="md:hidden">
                <div class="px-2 pt-2 pb-3 space-y-1 bg-white/95 backdrop-blur-md rounded-lg mt-2 shadow-lg border border-blue-100">
                    <a href="#inicio" @click="mobileMenuOpen = false" class="block px-3 py-2 text-gray-800 hover:text-primary hover:bg-blue-50 rounded-md transition-colors font-medium">Inicio</a>
                    <a href="#beneficios" @click="mobileMenuOpen = false" class="block px-3 py-2 text-gray-800 hover:text-primary hover:bg-blue-50 rounded-md transition-colors font-medium">Beneficios</a>
                    <a href="#precios" @click="mobileMenuOpen = false" class="block px-3 py-2 text-gray-800 hover:text-primary hover:bg-blue-50 rounded-md transition-colors font-medium">Precios</a>
                    <a href="#faq" @click="mobileMenuOpen = false" class="block px-3 py-2 text-gray-800 hover:text-primary hover:bg-blue-50 rounded-md transition-colors font-medium">FAQ</a>
                    <div class="border-t border-blue-100 pt-2">
                        @auth
                            <a href="{{ route('dashboard') }}" @click="mobileMenuOpen = false" class="block px-3 py-2 text-primary hover:bg-blue-50 rounded-md transition-colors font-medium">
                                <i class="fas fa-cog mr-2"></i>Admin
                            </a>
                        @else
                            <a href="{{ route('login') }}" @click="mobileMenuOpen = false" class="block px-3 py-2 text-primary hover:bg-blue-50 rounded-md transition-colors font-medium">
                                <i class="fas fa-sign-in-alt mr-2"></i>Ingresa
                            </a>
                        @endauth
                        <a href="{{ route('register') }}" @click="mobileMenuOpen = false" class="w-full mt-2 bg-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-all block text-center">
                            Empezar Gratis
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="inicio" class="hero-bg min-h-screen flex items-center relative overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="text-white">
                    <h1 class="text-5xl lg:text-7xl font-black mb-6 leading-tight">
                        Deja de <span class="text-yellow-300">Perder Dinero</span> en Gestión Fiscal de Amazon
                    </h1>
                    <p class="text-xl lg:text-2xl mb-8 text-blue-100 font-medium">
                        Convierte 8 horas de trabajo manual en 15 minutos automáticos.
                        <strong>Ahorra +600€/mes</strong> mientras eliminas errores costosos.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 mb-8">
                        <a href="{{ route('register') }}" class="bg-yellow-400 text-black px-8 py-4 rounded-xl font-black text-lg hover:bg-yellow-300 transition-all transform hover:scale-105 shadow-2xl text-center">
                            <i class="fas fa-rocket mr-2"></i>EMPEZAR GRATIS HOY
                        </a>
                        <button class="glass bg-white/80 text-black px-8 py-4 rounded-xl font-semibold text-lg hover:bg-white transition-all">
                            <i class="fas fa-play mr-2"></i>Ver Demo
                        </button>
                    </div>

                    <div class="flex items-center space-x-6 text-blue-100">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            <span>Sin setup complicado</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            <span>Resultados inmediatos</span>
                        </div>
                    </div>
                </div>

                <div class="lg:text-right">
                    <div class="glass p-8 rounded-3xl animate-float">
                        <h3 class="text-2xl font-bold text-white mb-4">Resultados Reales</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center text-white">
                                <span>Tiempo ahorrado/mes:</span>
                                <span class="font-bold text-green-400">32 horas</span>
                            </div>
                            <div class="flex justify-between items-center text-white">
                                <span>Ahorro económico:</span>
                                <span class="font-bold text-green-400">€600+</span>
                            </div>
                            <div class="flex justify-between items-center text-white">
                                <span>Errores eliminados:</span>
                                <span class="font-bold text-green-400">99.8%</span>
                            </div>
                            <div class="flex justify-between items-center text-white">
                                <span>ROI mes 1:</span>
                                <span class="font-bold text-green-400">2400%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating elements -->
        <div class="absolute top-20 right-20 w-20 h-20 glass rounded-full animate-float" style="animation-delay: -2s;"></div>
        <div class="absolute bottom-40 left-10 w-16 h-16 glass rounded-full animate-float" style="animation-delay: -4s;"></div>
    </section>

    <!-- Demo Upload Section (Static Visual) -->
    <section class="py-20 bg-gradient-to-br from-blue-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <!-- Left side: Description -->
                <div>
                    <div class="inline-block bg-blue-100 text-primary px-4 py-2 rounded-lg text-sm font-semibold mb-4">
                        <i class="fas fa-eye mr-2"></i>Así de Fácil
                    </div>
                    <h2 class="text-4xl lg:text-5xl font-black text-gray-900 mb-6">
                        Sube tus Reportes de <span class="text-primary">Amazon</span>
                    </h2>
                    <p class="text-xl text-gray-600 mb-6">
                        Simplemente arrastra tu archivo CSV de Amazon a nuestra plataforma y deja que la automatización haga su magia.
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-green-100 text-green-600">
                                    <i class="fas fa-bolt text-lg"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Procesamiento Instantáneo</h3>
                                <p class="text-gray-600">Clasificación automática de miles de transacciones</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-blue-100 text-primary">
                                    <i class="fas fa-file-alt text-lg"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Informes Fiscales</h3>
                                <p class="text-gray-600">Reportes listos para presentar a Hacienda</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-yellow-100 text-yellow-600">
                                    <i class="fas fa-shield-alt text-lg"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">100% Seguro y Preciso</h3>
                                <p class="text-gray-600">Tus datos están encriptados y protegidos</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Right side: Static Upload Visual Mockup -->
                <div class="relative">
                    <!-- Upload Card (Static Visual) -->
                    <div class="bg-white rounded-2xl shadow-2xl p-8 border border-gray-100">
                        <!-- Header with checkmark -->
                        <div class="flex items-center justify-center mb-6">
                            <h3 class="text-2xl font-black text-gray-900 uppercase tracking-wide">Subir Archivos</h3>
                            <div class="h-12 w-12 rounded-full bg-green-500 flex items-center justify-center shadow-lg ml-4">
                                <i class="fas fa-check text-white text-xl"></i>
                            </div>
                        </div>

                        <!-- Animated Drop Zone Visual with 3 CSV Files -->
                        <div class="flex flex-col items-center justify-center w-full h-80 border-2 border-dashed border-primary bg-blue-50 rounded-xl relative overflow-hidden"
                             x-data="{
                                 filePhase: 0,
                                 init() {
                                     this.startAnimation();
                                 },
                                 startAnimation() {
                                     // Phase 1: All files appear together
                                     this.filePhase = 1;

                                     setTimeout(() => {
                                         // Phase 2: First file enters box
                                         this.filePhase = 2;
                                     }, 1500);

                                     setTimeout(() => {
                                         // Phase 3: Second file enters box
                                         this.filePhase = 3;
                                     }, 3500);

                                     setTimeout(() => {
                                         // Phase 4: Third file enters box
                                         this.filePhase = 4;
                                     }, 5500);

                                     setTimeout(() => {
                                         // Phase 0: Reset - all files hidden
                                         this.filePhase = 0;
                                     }, 7500);

                                     setTimeout(() => {
                                         // Loop again
                                         this.startAnimation();
                                     }, 8000);
                                 }
                             }">

                            <!-- Three CSV Files -->
                            <div class="absolute inset-0 pointer-events-none">
                                <!-- Left CSV - Rotated Left (File 1 - enters first) -->
                                <div class="absolute" style="left: 20%; top: 10%;">
                                    <i class="fas fa-file-csv text-5xl text-primary"
                                       :style="{
                                           opacity: filePhase === 0 ? '0' :
                                                   filePhase === 1 ? '1' :
                                                   filePhase >= 2 && filePhase <= 4 ? '0' : '1',
                                           transform: filePhase === 0 || filePhase >= 5 ? 'translateY(0px) rotate(-25deg) scale(1)' :
                                                     filePhase === 1 ? 'translateY(0px) rotate(-25deg) scale(1)' :
                                                     'translate(130px, 100px) rotate(0deg) scale(0.2)',
                                           transition: filePhase === 2 ? 'transform 1.5s ease-in-out, opacity 0.3s 1.5s ease-in-out' :
                                                      filePhase === 0 ? 'none' :
                                                      'opacity 0.3s ease-in-out',
                                           textShadow: '2px 2px 4px rgba(0,0,0,0.1)'
                                       }"></i>
                                </div>

                                <!-- Center CSV - Straight (File 2 - enters second) -->
                                <div class="absolute" style="left: 50%; top: 5%; transform: translateX(-50%);">
                                    <i class="fas fa-file-csv text-6xl text-blue-600"
                                       :style="{
                                           opacity: filePhase === 0 ? '0' :
                                                   filePhase === 1 || filePhase === 2 ? '1' :
                                                   filePhase >= 3 && filePhase <= 4 ? '0' : '1',
                                           transform: filePhase === 0 || filePhase >= 5 ? 'translateY(0px) scale(1)' :
                                                     filePhase === 1 || filePhase === 2 ? 'translateY(0px) scale(1)' :
                                                     'translateY(115px) scale(0.2)',
                                           transition: filePhase === 3 ? 'transform 1.5s ease-in-out, opacity 0.3s 1.5s ease-in-out' :
                                                      filePhase === 0 ? 'none' :
                                                      'opacity 0.3s ease-in-out',
                                           textShadow: '2px 2px 4px rgba(0,0,0,0.1)'
                                       }"></i>
                                </div>

                                <!-- Right CSV - Rotated Right (File 3 - enters third) -->
                                <div class="absolute" style="right: 20%; top: 10%;">
                                    <i class="fas fa-file-csv text-5xl text-indigo-600"
                                       :style="{
                                           opacity: filePhase === 0 ? '0' :
                                                   filePhase >= 1 && filePhase <= 3 ? '1' :
                                                   filePhase === 4 ? '0' : '1',
                                           transform: filePhase === 0 || filePhase >= 5 ? 'translateY(0px) rotate(25deg) scale(1)' :
                                                     filePhase >= 1 && filePhase <= 3 ? 'translateY(0px) rotate(25deg) scale(1)' :
                                                     'translate(-130px, 100px) rotate(0deg) scale(0.2)',
                                           transition: filePhase === 4 ? 'transform 1.5s ease-in-out, opacity 0.3s 1.5s ease-in-out' :
                                                      filePhase === 0 ? 'none' :
                                                      'opacity 0.3s ease-in-out',
                                           textShadow: '2px 2px 4px rgba(0,0,0,0.1)'
                                       }"></i>
                                </div>
                            </div>

                            <div class="flex flex-col items-center justify-center pt-5 pb-6 text-center pointer-events-none relative z-10">
                                <!-- Open Box Icon - Always Visible at Center -->
                                <div class="mt-20 mb-6 relative h-28 w-28 flex items-center justify-center">
                                    <i class="fas fa-box-open text-orange-500"
                                       style="font-size: 6.5rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.1);"></i>
                                </div>

                                <!-- Text -->
                                <p class="mb-2 text-gray-700 text-lg px-4">
                                    <span class="font-bold text-gray-900">¡Así de simple!</span> Arrastra, suelta y listo.<br>
                                    <span class="text-base text-gray-600">En segundos tu trabajo está hecho</span>
                                </p>
                            </div>
                        </div>

                        <!-- Amazon Logo (Complete SVG) -->
                        <div class="mt-8 flex items-center justify-center">
                            <div class="px-10 py-5">
                                <svg xmlns:svg="http://www.w3.org/2000/svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" class="h-12" viewBox="0 0 603 182" style="fill:#221f1f">
                                    <path d="m 374.00642,142.18404 c -34.99948,25.79739 -85.72909,39.56123 -129.40634,39.56123 -61.24255,0 -116.37656,-22.65135 -158.08757,-60.32496 -3.2771,-2.96252 -0.34083,-6.9999 3.59171,-4.69283 45.01431,26.19064 100.67269,41.94697 158.16623,41.94697 38.774689,0 81.4295,-8.02237 120.6499,-24.67006 5.92501,-2.51683 10.87999,3.88009 5.08607,8.17965" style="fill:#ff9900"/>
                                    <path d="m 388.55678,125.53635 c -4.45688,-5.71527 -29.57261,-2.70033 -40.84585,-1.36327 -3.43442,0.41947 -3.95874,-2.56925 -0.86517,-4.71905 20.00346,-14.07844 52.82696,-10.01483 56.65462,-5.2958 3.82764,4.74526 -0.99624,37.64741 -19.79373,53.35128 -2.88385,2.41195 -5.63662,1.12734 -4.35198,-2.07113 4.2209,-10.53917 13.68519,-34.16054 9.20211,-39.90203" style="fill:#ff9900"/>
                                    <path d="M 348.49744,20.06598 V 6.38079 c 0,-2.07113 1.57301,-3.46062 3.46062,-3.46062 h 61.26875 c 1.96628,0 3.53929,1.41571 3.53929,3.46062 v 11.71893 c -0.0262,1.96626 -1.67788,4.53551 -4.61418,8.59912 l -31.74859,45.32893 c 11.79759,-0.28837 24.25059,1.46814 34.94706,7.49802 2.41195,1.36327 3.06737,3.35575 3.25089,5.32203 V 99.4506 c 0,1.99248 -2.20222,4.32576 -4.5093,3.1198 -18.84992,-9.88376 -43.887,-10.95865 -64.72939,0.10487 -2.12356,1.15354 -4.35199,-1.15354 -4.35199,-3.14602 V 85.66054 c 0,-2.22843 0.0262,-6.02989 2.25463,-9.41186 l 36.78224,-52.74829 h -32.01076 c -1.96626,0 -3.53927,-1.38948 -3.53927,-3.43441"/>
                                    <path d="m 124.99883,105.45424 h -18.64017 c -1.78273,-0.13107 -3.19845,-1.46813 -3.32954,-3.17224 V 6.61676 c 0,-1.91383 1.59923,-3.43442 3.59171,-3.43442 h 17.38176 c 1.80898,0.0786 3.25089,1.46814 3.38199,3.19845 v 12.50545 h 0.34082 c 4.53551,-12.08598 13.05597,-17.7226 24.53896,-17.7226 11.66649,0 18.95477,5.63662 24.19814,17.7226 4.5093,-12.08598 14.76008,-17.7226 25.74495,-17.7226 7.81262,0 16.35931,3.22467 21.57646,10.46052 5.89879,8.04857 4.69281,19.74128 4.69281,29.99208 l -0.0262,60.37739 c 0,1.91383 -1.59923,3.46061 -3.59171,3.46061 h -18.61397 c -1.86138,-0.13107 -3.35574,-1.62543 -3.35574,-3.46061 V 51.29025 c 0,-4.03739 0.36702,-14.10466 -0.52434,-17.93233 -1.38949,-6.42311 -5.55797,-8.23209 -10.95865,-8.23209 -4.5093,0 -9.22833,3.01494 -11.14216,7.83885 -1.91383,4.8239 -1.73031,12.89867 -1.73031,18.32557 v 50.70338 c 0,1.91383 -1.59923,3.46061 -3.59171,3.46061 h -18.61395 c -1.88761,-0.13107 -3.35576,-1.62543 -3.35576,-3.46061 L 152.946,51.29025 c 0,-10.67025 1.75651,-26.37415 -11.48298,-26.37415 -13.39682,0 -12.87248,15.31063 -12.87248,26.37415 v 50.70338 c 0,1.91383 -1.59923,3.46061 -3.59171,3.46061"/>
                                    <path d="m 469.51439,1.16364 c 27.65877,0 42.62858,23.75246 42.62858,53.95427 0,29.17934 -16.54284,52.32881 -42.62858,52.32881 -27.16066,0 -41.94697,-23.75246 -41.94697,-53.35127 0,-29.78234 14.96983,-52.93181 41.94697,-52.93181 m 0.15729,19.53156 c -13.73761,0 -14.60278,18.71881 -14.60278,30.38532 0,11.69271 -0.18352,36.65114 14.44549,36.65114 14.44548,0 15.12712,-20.13452 15.12712,-32.40403 0,-8.07477 -0.34082,-17.72257 -2.779,-25.3779 -2.09735,-6.65906 -6.26581,-9.25453 -12.19083,-9.25453"/>
                                    <path d="M 548.00762,105.45424 H 529.4461 c -1.86141,-0.13107 -3.35577,-1.62543 -3.35577,-3.46061 l -0.0262,-95.69149 c 0.1573,-1.75653 1.7041,-3.1198 3.59171,-3.1198 h 17.27691 c 1.62543,0.0786 2.96249,1.17976 3.32954,2.67412 v 14.62899 h 0.3408 c 5.21717,-13.0822 12.53165,-19.32181 25.40412,-19.32181 8.36317,0 16.51662,3.01494 21.75999,11.27324 4.87633,7.65532 4.87633,20.5278 4.87633,29.78233 v 60.22011 c -0.20973,1.67786 -1.75653,3.01492 -3.59169,3.01492 h -18.69262 c -1.70411,-0.13107 -3.11982,-1.38948 -3.30332,-3.01492 V 50.47753 c 0,-10.46052 1.20597,-25.77117 -11.66651,-25.77117 -4.5355,0 -8.70399,3.04117 -10.77512,7.65532 -2.62167,5.84637 -2.96249,11.66651 -2.96249,18.11585 v 51.5161 c -0.0262,1.91383 -1.65166,3.46061 -3.64414,3.46061"/>
                                    <path id="path30" d="M 55.288261,59.75829 V 55.7209 c -13.475471,0 -27.711211,2.88385 -27.711211,18.77125 0,8.04857 4.16847,13.50169 11.32567,13.50169 5.24337,0 9.93618,-3.22467 12.8987,-8.46805 3.670341,-6.44935 3.486841,-12.50544 3.486841,-19.7675 m 18.79747,45.43378 c -1.23219,1.10111 -3.01495,1.17976 -4.40444,0.4457 -6.18716,-5.1385 -7.28828,-7.52423 -10.69647,-12.42678 -10.224571,10.4343 -17.460401,13.55409 -30.726141,13.55409 -15.67768,0 -27.89471,-9.67401 -27.89471,-29.04824 0,-15.12713 8.20587,-25.43035 19.87236,-30.46398 10.1197,-4.45688 24.25058,-5.24337 35.051931,-6.47556 v -2.41195 c 0,-4.43066 0.34082,-9.67403 -2.25465,-13.50167 -2.280881,-3.43442 -6.632861,-4.85013 -10.460531,-4.85013 -7.10475,0 -13.44924,3.64414 -14.99603,11.19459 -0.31461,1.67789 -1.5468,3.32955 -3.22467,3.4082 L 6.26276,32.67628 C 4.74218,32.33548 3.0643,31.10327 3.48377,28.76999 7.65225,6.85271 27.44596,0.24605 45.16856,0.24605 c 9.071011,0 20.921021,2.41195 28.078221,9.28076 9.07104,8.46804 8.20587,19.7675 8.20587,32.06321 v 29.04826 c 0,8.73022 3.61794,12.55786 7.02613,17.27691 1.20597,1.67786 1.46814,3.69656 -0.05244,4.95497 -3.80144,3.17225 -10.56538,9.07104 -14.28819,12.37436 l -0.05242,-0.0525"/>
                                    <use xlink:href="#path30" transform="translate(244.36719)"/>
                                </svg>
                            </div>
                        </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Problem Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl lg:text-5xl font-black text-gray-900 mb-6">
                    El Problema que te está <span class="text-red-600">Sangrando Dinero</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Cada mes que pasa sin automatizar tu gestión fiscal de Amazon es dinero perdido para siempre.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="glass-dark p-8 rounded-2xl">
                    <div class="text-red-500 text-4xl mb-4">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">8 Horas Perdidas</h3>
                    <p class="text-gray-600">Por cada cliente de Amazon, pierdes 8 horas al mes en trabajo manual que debería estar automatizado.</p>
                </div>

                <div class="glass-dark p-8 rounded-2xl">
                    <div class="text-red-500 text-4xl mb-4">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Errores Fiscales</h3>
                    <p class="text-gray-600">Una declaración incorrecta puede costarte hasta el 150% del importe no declarado. ¿Puedes permitírtelo?</p>
                </div>

                <div class="glass-dark p-8 rounded-2xl">
                    <div class="text-red-500 text-4xl mb-4">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Oportunidad Perdida</h3>
                    <p class="text-gray-600">Mientras pierdes tiempo en tareas manuales, podrías estar gestionando más clientes y facturando más.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Solution Benefits -->
    <section id="beneficios" class="py-20 bg-gradient-to-br from-blue-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl lg:text-5xl font-black text-gray-900 mb-6">
                    La Solución que <span class="text-green-600">Multiplica tus Ganancias</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Transforma tu gestoría en una máquina de hacer dinero con automatización inteligente.
                </p>
            </div>

            <div class="grid lg:grid-cols-2 gap-12 items-center mb-16 landing-contrast">
                <div>
                    <h3 class="text-3xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-robot text-primary mr-3"></i>
                        Automatización Total
                    </h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                            <span class="text-lg text-gray-800"><strong>Clasificación automática</strong> de miles de transacciones en segundos</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                            <span class="text-lg text-gray-800"><strong>Generación instantánea</strong> de informes para modelos 349 y 369</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                            <span class="text-lg text-gray-800"><strong>Validación en tiempo real</strong> de números de IVA</span>
                        </li>
                    </ul>
                </div>
                <div class="glass p-8 rounded-3xl bg-white/90 text-gray-900">
                    <div class="text-center">
                        <div class="text-6xl font-black text-primary mb-4">8h → 15min</div>
                        <p class="text-xl text-gray-700">Reducción de tiempo por cliente</p>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-12 items-center landing-contrast">
                <div class="lg:order-2">
                    <h3 class="text-3xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-shield-alt text-green-500 mr-3"></i>
                        Protección Total
                    </h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                            <span class="text-lg text-gray-800"><strong>Elimina multas</strong> por declaraciones incorrectas</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                            <span class="text-lg text-gray-800"><strong>Cumplimiento automático</strong> con normativas europeas</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check-circle text-green-500 mr-3 mt-1"></i>
                            <span class="text-lg text-gray-800"><strong>Documentación completa</strong> para inspecciones</span>
                        </li>
                    </ul>
                </div>
                <div class="lg:order-1 glass p-8 rounded-3xl">
                    <div class="text-center">
                        <div class="text-6xl font-black text-green-500 mb-4">€0</div>
                        <p class="text-xl text-gray-700">En multas desde el día 1</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @livewire('landing.pricing-section')

    <!-- Pricing Additional Info -->
    <section class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p class="text-lg text-gray-600 mb-4">
                    <i class="fas fa-clock text-primary mr-2"></i>
                    <strong>Oferta limitada:</strong> 7 días de prueba gratuita + setup sin coste
                </p>
                <div class="flex justify-center items-center space-x-6 text-gray-500">
                    <span><i class="fas fa-credit-card mr-2"></i>Sin permanencia</span>
                    <span><i class="fas fa-shield-alt mr-2"></i>Garantía 30 días</span>
                    <span><i class="fas fa-headset mr-2"></i>Soporte 24/7</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Social Proof -->
    <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-white mb-6">
                    Más de 500 Gestorías ya Confían en MyTaxEU
                </h2>
            </div>

            <div class="grid md:grid-cols-2 gap-8">
                <div class="glass p-8 rounded-2xl">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400 text-xl">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <p class="text-white text-lg mb-4">
                        "Pasé de trabajar 60 horas/semana a 35 horas gestionando el doble de clientes. MyTaxEU me devolvió mi vida y multiplicó mis ingresos."
                    </p>
                    <div class="font-semibold text-blue-200">
                        Carlos Martínez - GM Asesores Fiscales
                    </div>
                </div>

                <div class="glass p-8 rounded-2xl">
                    <div class="flex items-center mb-4">
                        <div class="flex text-yellow-400 text-xl">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <p class="text-white text-lg mb-4">
                        "Mi gestor redujo sus tarifas en un 70% gracias a MyTaxEU. Ahora tengo la tranquilidad de que todo está perfecto y pago mucho menos."
                    </p>
                    <div class="font-semibold text-blue-200">
                        Miguel Sánchez - Vendedor Amazon Europa
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-20 bg-gray-50" x-data="{
        openFaq: null,
        toggleFaq(index) {
            this.openFaq = this.openFaq === index ? null : index;
        }
    }">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-6">
                    Preguntas Frecuentes
                </h2>
            </div>

            <div class="space-y-6">
                <div class="glass-dark rounded-xl overflow-hidden">
                    <button @click="toggleFaq(1)" class="w-full text-left font-semibold text-lg text-gray-900 flex justify-between items-center p-6">
                        ¿Realmente puedo ahorrar 8 horas por cliente?
                        <i class="fas transform transition-all duration-300 ease-in-out" :class="openFaq === 1 ? 'fa-minus rotate-180' : 'fa-plus'"></i>
                    </button>
                    <div class="overflow-hidden"
                         x-show="openFaq === 1"
                         x-transition:enter="transition-all duration-300 ease-out"
                         x-transition:enter-start="opacity-0 max-h-0"
                         x-transition:enter-end="opacity-100 max-h-96"
                         x-transition:leave="transition-all duration-200 ease-in"
                         x-transition:leave-start="opacity-100 max-h-96"
                         x-transition:leave-end="opacity-0 max-h-0">
                        <div class="px-6 pb-6 text-gray-600">
                            <p>Sí. Nuestros usuarios reportan una reducción media de 8 horas por cliente al mes. La automatización elimina la clasificación manual de transacciones, la validación de IVA y la generación de informes.</p>
                        </div>
                    </div>
                </div>

                <div class="glass-dark rounded-xl overflow-hidden">
                    <button @click="toggleFaq(2)" class="w-full text-left font-semibold text-lg text-gray-900 flex justify-between items-center p-6">
                        ¿Qué pasa si cometo un error en las declaraciones?
                        <i class="fas transform transition-all duration-300 ease-in-out" :class="openFaq === 2 ? 'fa-minus rotate-180' : 'fa-plus'"></i>
                    </button>
                    <div class="overflow-hidden"
                         x-show="openFaq === 2"
                         x-transition:enter="transition-all duration-300 ease-out"
                         x-transition:enter-start="opacity-0 max-h-0"
                         x-transition:enter-end="opacity-100 max-h-96"
                         x-transition:leave="transition-all duration-200 ease-in"
                         x-transition:leave-start="opacity-100 max-h-96"
                         x-transition:leave-end="opacity-0 max-h-0">
                        <div class="px-6 pb-6 text-gray-600">
                            <p>Con MyTaxEU, los errores son prácticamente imposibles. El sistema valida automáticamente cada transacción y clasifica según la normativa vigente. Garantizamos precisión del 99.8%.</p>
                        </div>
                    </div>
                </div>

                <div class="glass-dark rounded-xl overflow-hidden">
                    <button @click="toggleFaq(3)" class="w-full text-left font-semibold text-lg text-gray-900 flex justify-between items-center p-6">
                        ¿Cuánto tiempo necesito para ver resultados?
                        <i class="fas transform transition-all duration-300 ease-in-out" :class="openFaq === 3 ? 'fa-minus rotate-180' : 'fa-plus'"></i>
                    </button>
                    <div class="overflow-hidden"
                         x-show="openFaq === 3"
                         x-transition:enter="transition-all duration-300 ease-out"
                         x-transition:enter-start="opacity-0 max-h-0"
                         x-transition:enter-end="opacity-100 max-h-96"
                         x-transition:leave="transition-all duration-200 ease-in"
                         x-transition:leave-start="opacity-100 max-h-96"
                         x-transition:leave-end="opacity-0 max-h-0">
                        <div class="px-6 pb-6 text-gray-600">
                            <p>Los resultados son inmediatos. Desde el primer cliente que proceses, ya estarás ahorrando tiempo. El setup inicial toma menos de 30 minutos.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section></thinking>
    <!-- Final CTA -->
    <section class="py-20 hero-bg">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl lg:text-5xl font-black text-white mb-6">
                ¿Listo para Transformar tu Gestoría?
            </h2>
            <p class="text-xl text-blue-100 mb-8 max-w-2xl mx-auto">
                Únete a las 500+ gestorías que ya han automatizado su gestión fiscal de Amazon y están facturando más trabajando menos.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center mb-8">
                <a href="{{ route('register') }}" class="bg-yellow-400 text-black px-8 py-4 rounded-xl font-black text-lg hover:bg-yellow-300 transition-all transform hover:scale-105 shadow-2xl text-center">
                    <i class="fas fa-rocket mr-2"></i>EMPEZAR GRATIS AHORA
                </a>
                <button class="glass text-white px-8 py-4 rounded-xl font-semibold text-lg hover:bg-white hover:bg-opacity-20 transition-all">
                    <i class="fas fa-phone mr-2"></i>Hablar con Experto
                </button>
            </div>

            <div class="text-blue-200 text-sm">
                ⚡ Setup en 30 minutos • 💰 7 días gratis • 🛡️ Garantía 30 días
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <div class="text-3xl font-bold text-primary mb-4">MyTaxEU</div>
                <p class="text-gray-400 mb-8 max-w-2xl mx-auto">
                    Automatizamos la gestión fiscal de Amazon para que puedas enfocarte en hacer crecer tu negocio.
                </p>

                <div class="flex justify-center space-x-8 mb-8">
                    <a href="#" class="text-gray-400 hover:text-white transition-colors">Aviso Legal</a>
                    <a href="#" class="text-gray-400 hover:text-white transition-colors">Privacidad</a>
                    <a href="#" class="text-gray-400 hover:text-white transition-colors">Términos</a>
                    <a href="#" class="text-gray-400 hover:text-white transition-colors">Contacto</a>
                </div>

                <div class="text-gray-400 text-sm">
                    © 2024 MyTaxEU. Todos los derechos reservados.
                </div>
            </div>
        </div>
    </footer>
</div>
@endsection
