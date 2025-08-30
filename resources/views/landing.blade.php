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

                <div class="flex items-center space-x-4">
                    <a href="admin.html" class="bg-blue-50 hover:bg-blue-100 px-4 py-2 rounded-lg text-primary font-medium transition-all">
                        <i class="fas fa-cog mr-2"></i>Admin
                    </a>
                    <button class="bg-primary text-white px-6 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-all transform hover:scale-105 shadow-md">
                        Empezar Gratis
                    </button>
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
                        <a href="admin.html" @click="mobileMenuOpen = false" class="block px-3 py-2 text-primary hover:bg-blue-50 rounded-md transition-colors font-medium">
                            <i class="fas fa-cog mr-2"></i>Admin
                        </a>
                        <button @click="mobileMenuOpen = false" class="w-full mt-2 bg-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-all">
                            Empezar Gratis
                        </button>
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
                        <button class="bg-yellow-400 text-black px-8 py-4 rounded-xl font-black text-lg hover:bg-yellow-300 transition-all transform hover:scale-105 shadow-2xl">
                            <i class="fas fa-rocket mr-2"></i>EMPEZAR GRATIS HOY
                        </button>
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
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Multas Costosas</h3>
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

    <!-- Pricing Section -->
    <section id="precios" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl lg:text-5xl font-black text-gray-900 mb-6">
                    Invierte <span class="text-primary">€25/mes</span>, Ahorra <span class="text-green-600">€600/mes</span>
                </h2>
                <p class="text-xl text-gray-600">
                    ROI del 2400% desde el primer mes. Literalmente imposible de perder.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <!-- Individual Plan -->
                <div class="glass-dark p-8 rounded-3xl relative">
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Plan Starter</h3>
                        <div class="text-4xl font-black text-primary mb-2">€25</div>
                        <div class="text-gray-600 mb-6">+ IVA /mes</div>
                        <ul class="space-y-3 text-left mb-8">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>1 cliente Amazon/mes</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Informes automáticos</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Soporte por email</span>
                            </li>
                        </ul>
                        <button class="w-full bg-primary text-white py-3 rounded-xl font-semibold hover:bg-blue-700 transition-all">
                            Empezar Gratis
                        </button>
                    </div>
                </div>

                <!-- Business Plan (Highlighted) -->
                <div class="bg-white p-8 rounded-3xl relative border-4 border-yellow-400 transform scale-105 shadow-2xl">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                        <span class="bg-yellow-400 text-black px-4 py-2 rounded-full font-bold text-sm">MÁS POPULAR</span>
                    </div>
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Plan Business</h3>
                        <div class="text-4xl font-black text-primary mb-2">€125</div>
                        <div class="text-gray-600 mb-6">+ IVA /mes</div>
                        <ul class="space-y-3 text-left mb-8">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>5 clientes Amazon/mes</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Todo del plan anterior</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Soporte prioritario</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Ahorro: €3000/mes</span>
                            </li>
                        </ul>
                        <button class="w-full bg-yellow-400 text-black py-3 rounded-xl font-bold hover:bg-yellow-300 transition-all">
                            EMPEZAR AHORA
                        </button>
                    </div>
                </div>

                <!-- Enterprise Plan -->
                <div class="glass-dark p-8 rounded-3xl relative">
                    <div class="text-center">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Plan Enterprise</h3>
                        <div class="text-4xl font-black text-primary mb-2">€500</div>
                        <div class="text-gray-600 mb-6">+ IVA /mes</div>
                        <ul class="space-y-3 text-left mb-8">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>20 clientes Amazon/mes</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Todo de planes anteriores</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Soporte dedicado</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                <span>Ahorro: €12000/mes</span>
                            </li>
                        </ul>
                        <button class="w-full bg-primary text-white py-3 rounded-xl font-semibold hover:bg-blue-700 transition-all">
                            Contactar
                        </button>
                    </div>
                </div>
            </div>

            <div class="text-center mt-12">
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
                <div class="glass-dark p-6 rounded-xl">
                    <button @click="toggleFaq(1)" class="w-full text-left font-semibold text-lg text-gray-900 flex justify-between items-center">
                        ¿Realmente puedo ahorrar 8 horas por cliente?
                        <i class="fas transform transition-transform duration-200" :class="openFaq === 1 ? 'fa-minus rotate-180' : 'fa-plus'"></i>
                    </button>
                    <div x-show="openFaq === 1" x-transition class="mt-4 text-gray-600">
                        <p>Sí. Nuestros usuarios reportan una reducción media de 8 horas por cliente al mes. La automatización elimina la clasificación manual de transacciones, la validación de IVA y la generación de informes.</p>
                    </div>
                </div>

                <div class="glass-dark p-6 rounded-xl">
                    <button @click="toggleFaq(2)" class="w-full text-left font-semibold text-lg text-gray-900 flex justify-between items-center">
                        ¿Qué pasa si cometo un error en las declaraciones?
                        <i class="fas transform transition-transform duration-200" :class="openFaq === 2 ? 'fa-minus rotate-180' : 'fa-plus'"></i>
                    </button>
                    <div x-show="openFaq === 2" x-transition class="mt-4 text-gray-600">
                        <p>Con MyTaxEU, los errores son prácticamente imposibles. El sistema valida automáticamente cada transacción y clasifica según la normativa vigente. Garantizamos precisión del 99.8%.</p>
                    </div>
                </div>

                <div class="glass-dark p-6 rounded-xl">
                    <button @click="toggleFaq(3)" class="w-full text-left font-semibold text-lg text-gray-900 flex justify-between items-center">
                        ¿Cuánto tiempo necesito para ver resultados?
                        <i class="fas transform transition-transform duration-200" :class="openFaq === 3 ? 'fa-minus rotate-180' : 'fa-plus'"></i>
                    </button>
                    <div x-show="openFaq === 3" x-transition class="mt-4 text-gray-600">
                        <p>Los resultados son inmediatos. Desde el primer cliente que proceses, ya estarás ahorrando tiempo. El setup inicial toma menos de 30 minutos.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
                <button class="bg-yellow-400 text-black px-8 py-4 rounded-xl font-black text-lg hover:bg-yellow-300 transition-all transform hover:scale-105 shadow-2xl">
                    <i class="fas fa-rocket mr-2"></i>EMPEZAR GRATIS AHORA
                </button>
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
