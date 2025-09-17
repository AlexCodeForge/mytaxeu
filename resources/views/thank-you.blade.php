<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>¡Gracias por tu compra! - MyTaxEU</title>

    <!-- Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Auto-redirect after 10 seconds -->
    <script>
        setTimeout(function() {
            window.location.href = '{{ route('dashboard') }}';
        }, 10000);
    </script>
</head>

<body class="h-full">
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <!-- Success Icon -->
                <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-green-100 mb-6">
                    <svg class="h-12 w-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>

                <!-- Thank You Message -->
                <h1 class="text-3xl font-bold text-gray-900 mb-4">
                    ¡Gracias por tu compra!
                </h1>

                <p class="text-lg text-gray-600 mb-8">
                    Tu suscripción ha sido activada exitosamente
                </p>

                @if($subscriptionData)
                    <!-- Subscription Details -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-8 text-left">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Detalles de tu suscripción</h3>

                        @if(isset($subscriptionData['plan_name']))
                            <div class="mb-3">
                                <span class="text-sm font-medium text-gray-500">Plan:</span>
                                <span class="ml-2 text-sm text-gray-900 font-semibold">{{ $subscriptionData['plan_name'] }}</span>
                            </div>
                        @endif

                        @if(isset($subscriptionData['customer_name']))
                            <div class="mb-3">
                                <span class="text-sm font-medium text-gray-500">Cliente:</span>
                                <span class="ml-2 text-sm text-gray-900">{{ $subscriptionData['customer_name'] }}</span>
                            </div>
                        @endif

                        @if(isset($subscriptionData['customer_email']))
                            <div class="mb-3">
                                <span class="text-sm font-medium text-gray-500">Email:</span>
                                <span class="ml-2 text-sm text-gray-900">{{ $subscriptionData['customer_email'] }}</span>
                            </div>
                        @endif

                        @if(isset($subscriptionData['amount_total']) && isset($subscriptionData['currency']))
                            <div class="mb-3">
                                <span class="text-sm font-medium text-gray-500">Importe:</span>
                                <span class="ml-2 text-sm text-gray-900 font-semibold">
                                    {{ number_format($subscriptionData['amount_total'] / 100, 2) }} {{ $subscriptionData['currency'] }}
                                </span>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- What's Next -->
                <div class="bg-blue-50 rounded-lg p-6 mb-8 text-left">
                    <h3 class="text-lg font-semibold text-blue-900 mb-3">¿Qué sigue?</h3>
                    <ul class="text-sm text-blue-800 space-y-2">
                        <li class="flex items-start">
                            <svg class="h-5 w-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Tus créditos ya están disponibles en tu cuenta
                        </li>
                        <li class="flex items-start">
                            <svg class="h-5 w-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Puedes comenzar a subir tus archivos CSV inmediatamente
                        </li>
                        <li class="flex items-start">
                            <svg class="h-5 w-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Recibirás un email de confirmación en breve
                        </li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-4">
                    <a href="{{ route('dashboard') }}"
                       class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                        Ir al Dashboard
                    </a>

                    <a href="{{ route('billing') }}"
                       class="w-full flex justify-center py-3 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                        Ver Facturación
                    </a>
                </div>

                <!-- Auto Redirect Notice -->
                <p class="text-xs text-gray-500 mt-6">
                    Serás redirigido automáticamente al dashboard en 10 segundos
                </p>
            </div>
        </div>
    </div>

    <!-- Confetti Animation (Optional) -->
    <script>
        // Simple confetti effect on page load
        document.addEventListener('DOMContentLoaded', function() {
            // You can add a confetti library here if desired
            console.log('¡Suscripción exitosa! 🎉');
        });
    </script>
</body>
</html>
