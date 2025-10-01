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

        <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto items-start">
            @php
                $orderedPlans = collect();
                $starter = $plans->where('slug', 'starter')->first();
                $featured = $plans->where('is_featured', true)->first();
                $enterprise = $plans->where('slug', 'enterprise')->first();

                if($starter) $orderedPlans->push($starter);
                if($featured) $orderedPlans->push($featured);
                if($enterprise) $orderedPlans->push($enterprise);
            @endphp

            @foreach($orderedPlans as $plan)
                @if($plan->is_featured)
                    <!-- Featured Plan (Business) - Highlighted -->
                    <div class="relative md:-mt-4 md:mb-4">
                        <div class="bg-white p-8 rounded-3xl relative border-4 border-yellow-400 shadow-2xl">
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
                    <div class="glass-dark p-8 rounded-3xl relative">
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
                @elseif($plan->slug === 'enterprise')
                    <!-- Enterprise Plan -->
                    <div class="glass-dark p-8 rounded-3xl relative">
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
                                Contactar
                            </a>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="text-center mt-16">
            <p class="text-gray-600 mb-4">
                ¿Necesitas más de 20 clientes por mes?
            </p>
            <a href="#" class="text-primary font-semibold hover:underline">
                Contacta para plan personalizado
            </a>
        </div>
    </div>
</section>
