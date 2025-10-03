<!-- FAQ Section -->
<section id="faq" class="py-20 bg-gray-50" x-data="{
    openFaq: null,
    toggleFaq(index) {
        this.openFaq = this.openFaq === index ? null : index;
        console.log('FAQ toggled:', index, 'Open:', this.openFaq);
    }
}">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-6">
                Preguntas Frecuentes
            </h2>
            <p class="text-xl text-gray-600">
                Resuelve todas tus dudas sobre MyTaxEU
            </p>
        </div>

        <div class="space-y-4">
            @foreach($faqs as $index => $faq)
                <div class="glass-dark rounded-xl overflow-hidden border border-gray-200 hover:border-primary transition-colors duration-300">
                    <button
                        @click="toggleFaq({{ $index + 1 }})"
                        class="w-full text-left font-semibold text-lg text-gray-900 flex justify-between items-center p-6 hover:bg-gray-50 transition-colors duration-200"
                        aria-expanded="false"
                        :aria-expanded="openFaq === {{ $index + 1 }} ? 'true' : 'false'"
                    >
                        <span class="pr-4">{{ $faq['question'] }}</span>
                        <i class="fas transform transition-all duration-300 ease-in-out flex-shrink-0"
                           :class="openFaq === {{ $index + 1 }} ? 'fa-minus text-primary' : 'fa-plus text-gray-400'"></i>
                    </button>
                    <div class="overflow-hidden"
                         x-show="openFaq === {{ $index + 1 }}"
                         x-transition:enter="transition-all duration-300 ease-out"
                         x-transition:enter-start="opacity-0 max-h-0"
                         x-transition:enter-end="opacity-100 max-h-[500px]"
                         x-transition:leave="transition-all duration-200 ease-in"
                         x-transition:leave-start="opacity-100 max-h-[500px]"
                         x-transition:leave-end="opacity-0 max-h-0"
                         style="display: none;">
                        <div class="px-6 pb-6 text-gray-600 leading-relaxed border-t border-gray-100 pt-4">
                            <p>{{ $faq['answer'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Additional Help CTA -->
        <div class="mt-12 text-center">
            <div class="inline-flex items-center justify-center p-6 glass-dark rounded-xl border-2 border-primary/20">
                <div class="flex items-center space-x-3">
                    <div class="bg-primary/10 p-3 rounded-full">
                        <i class="fas fa-question-circle text-primary text-xl"></i>
                    </div>
                    <div class="text-left">
                        <p class="text-gray-900 font-semibold">¿No encuentras tu respuesta?</p>
                        <a href="mailto:soporte@mytaxeu.com" class="text-primary hover:text-blue-700 font-medium transition-colors">
                            Contacta con nuestro equipo de soporte →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

