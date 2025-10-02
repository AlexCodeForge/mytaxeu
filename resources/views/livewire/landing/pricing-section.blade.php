<!-- Pricing Section -->
<section id="precios" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            @php
                $starterPlan = $plans->where('slug', 'starter')->first();
                $starterPrice = $starterPlan ? number_format($starterPlan->monthly_price, 0) : '25';
            @endphp
            <h2 class="text-4xl lg:text-5xl font-black text-gray-900 mb-6">
                Invierte <span class="text-primary">€{{ $starterPrice }}/mes</span>, Ahorra <span class="text-green-600">€600/mes</span>
            </h2>
            <p class="text-xl text-gray-600">
                ROI del 2400% desde el primer mes. Literalmente imposible de perder.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto items-start">
            @php
                // Only show Free, Starter, and Business plans
                // Order: Free, Business (featured/popular), Starter
                $orderedPlans = collect();
                $free = $plans->where('slug', 'free')->first();
                $starter = $plans->where('slug', 'starter')->first();
                $featured = $plans->where('is_featured', true)->first();

                if($free) $orderedPlans->push($free);
                if($featured) $orderedPlans->push($featured);  // Business in the middle
                if($starter) $orderedPlans->push($starter);

                \Log::info('📊 Ordered Plans for Display (3 plans, popular in middle)', [
                    'count' => $orderedPlans->count(),
                    'order' => $orderedPlans->pluck('slug')->toArray()
                ]);
            @endphp

            @foreach($orderedPlans as $plan)
                @if($plan->slug === 'free')
                    <!-- Free Plan Card -->
                    <div class="glass-dark p-8 rounded-3xl relative border-2 border-primary transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-primary/20">
                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 z-10">
                            <span class="bg-primary text-white px-4 py-2 rounded-full font-bold text-sm">GRATIS</span>
                        </div>
                        <div class="text-center">
                            <h3 class="text-2xl font-bold text-gray-900 mb-4">{{ $plan->name }}</h3>
                            <div class="text-4xl font-black text-primary mb-2">€0</div>
                            <div class="text-gray-600 mb-6">Gratis para siempre</div>
                            <ul class="space-y-3 text-left mb-8">
                                @foreach($plan->features as $feature)
                                    <li class="flex items-center">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('register') }}" class="w-full bg-primary text-white py-3 rounded-xl font-semibold hover:bg-blue-700 transition-all block text-center">
                                Comenzar Gratis
                            </a>
                        </div>
                    </div>
                @elseif($plan->is_featured)
                    <!-- Featured Plan (Business) - Highlighted -->
                    <div class="relative md:-mt-4 md:mb-4">
                        <div class="bg-white p-8 rounded-3xl relative border-4 border-yellow-400 shadow-2xl transition-all duration-300 hover:scale-105 hover:shadow-[0_25px_50px_-12px_rgba(251,191,36,0.5)]">
                            <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 z-10">
                                <span class="bg-yellow-400 text-black px-4 py-2 rounded-full font-bold text-sm whitespace-nowrap">MÁS POPULAR</span>
                            </div>
                            <div class="text-center">
                                <h3 class="text-2xl font-bold text-gray-900 mb-4">{{ $plan->name }}</h3>
                                <div class="text-4xl font-black text-primary mb-2">€{{ number_format($plan->monthly_price, 0) }}</div>
                                <div class="text-gray-600 mb-6">+ IVA /mes</div>
                                <ul class="space-y-3 text-left mb-8">
                                    @foreach($plan->features as $feature)
                                        <li class="flex items-center">
                                            <i class="fas fa-check text-green-500 mr-2"></i>
                                            <span class="text-gray-800">{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('register') }}" class="w-full bg-yellow-400 text-black py-3 rounded-xl font-bold hover:bg-yellow-300 transition-all block text-center">
                                    EMPEZAR AHORA
                                </a>
                            </div>
                        </div>
                    </div>
                @elseif($plan->slug === 'starter')
                    <!-- Starter Plan -->
                    <div class="glass-dark p-8 rounded-3xl relative transition-all duration-300 hover:scale-105 hover:shadow-2xl">
                        <div class="text-center">
                            <h3 class="text-2xl font-bold text-gray-900 mb-4">{{ $plan->name }}</h3>
                            <div class="text-4xl font-black text-primary mb-2">€{{ number_format($plan->monthly_price, 0) }}</div>
                            <div class="text-gray-600 mb-6">+ IVA /mes</div>
                            <ul class="space-y-3 text-left mb-8">
                                @foreach($plan->features as $feature)
                                    <li class="flex items-center">
                                        <i class="fas fa-check text-green-500 mr-2"></i>
                                        <span>{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('register') }}" class="w-full bg-primary text-white py-3 rounded-xl font-semibold hover:bg-blue-700 transition-all block text-center">
                                Empezar Gratis
                            </a>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <!-- Enterprise / Custom Plans CTA -->
        <div class="text-center mt-16">
            <div class="max-w-2xl mx-auto glass-dark p-8 rounded-2xl border-2 border-primary">
                <div class="flex items-center justify-center mb-4">
                    <div class="bg-primary/10 p-3 rounded-full">
                        <i class="fas fa-building text-primary text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">
                    ¿Necesitas un Plan Enterprise?
                </h3>
                <p class="text-gray-600 mb-6">
                    Ofrecemos planes personalizados para empresas con más de 5 clientes Amazon, con soporte dedicado y funcionalidades avanzadas.
                </p>
                <a href="mailto:contacto@mytaxeu.com" class="inline-flex items-center bg-primary text-white px-8 py-4 rounded-xl font-bold hover:bg-blue-700 transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-envelope mr-2"></i>
                    Contactar para Plan Personalizado
                </a>
            </div>
        </div>
    </div>
</section>
