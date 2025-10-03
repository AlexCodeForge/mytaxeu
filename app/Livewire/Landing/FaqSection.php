<?php

declare(strict_types=1);

namespace App\Livewire\Landing;

use Illuminate\Support\Facades\Log;
use Livewire\Component;

class FaqSection extends Component
{
    public array $faqs = [];

    public function mount(): void
    {
        Log::info('📋 FAQ Section Component Mounted');

        $this->faqs = [
            [
                'question' => '¿Necesito tarjeta de crédito para empezar?',
                'answer' => 'No es necesario. Puedes registrarte completamente gratis sin proporcionar ningún método de pago. Accede a todas las funcionalidades del plan gratuito sin compromiso.'
            ],
            [
                'question' => '¿El plan gratuito tiene fecha de caducidad?',
                'answer' => 'Nunca caduca. Nuestro plan gratuito está disponible de forma permanente para procesar hasta 100 transacciones mensuales con todas las funciones esenciales incluidas.'
            ],
            [
                'question' => '¿Qué formatos de archivo puedo subir?',
                'answer' => 'La plataforma procesa exclusivamente archivos CSV con un tamaño máximo de 100MB. Estos son los archivos estándar que Amazon genera para reportes fiscales de IVA.'
            ],
            [
                'question' => '¿Dónde descargo los reportes fiscales de Amazon?',
                'answer' => 'En tu cuenta de vendedor de Amazon, navega a: Menú → Informes → Logística de Amazon → Informe de transacciones de IVA de Amazon. Descarga el archivo CSV del período que necesites analizar.'
            ],
            [
                'question' => '¿Cuánto tarda en procesarse mi archivo?',
                'answer' => 'El procesamiento es casi instantáneo. Si subiste el archivo correctamente, deberías ver los resultados en 1-2 minutos. Si no aparece, actualiza la página para visualizar tu reporte procesado.'
            ],
            [
                'question' => '¿Qué tipos de informes genera la plataforma?',
                'answer' => 'Generamos dos formatos: un PDF ejecutivo con el resumen fiscal listo para tu asesor, y un archivo Excel detallado con todas las transacciones clasificadas por tipo de IVA y país. Ambos descargables inmediatamente.'
            ],
            [
                'question' => '¿Cómo sé qué plan necesito contratar?',
                'answer' => 'Empieza con el plan gratuito y procesa un reporte. En el PDF generado verás el número total de transacciones. Con ese dato, elige el plan que mejor se ajuste a tu volumen mensual de operaciones.'
            ],
            [
                'question' => '¿Qué se considera una "transacción"?',
                'answer' => 'Cada transacción incluye: ventas completadas, devoluciones procesadas, liquidaciones de Amazon, y movimientos de inventario entre almacenes. Todo lo que aparece en tu reporte de IVA de Amazon cuenta como transacción.'
            ],
            [
                'question' => '¿Recibo factura por mi suscripción?',
                'answer' => 'Sí, todas las suscripciones incluyen factura automática. Completa tus datos fiscales (NIF/CIF) durante el proceso de pago para que aparezcan correctamente en tu factura descargable desde el panel.'
            ],
            [
                'question' => '¿Qué hago si encuentro un error al subir archivos?',
                'answer' => 'Asegúrate de no modificar el nombre del archivo descargado de Amazon. Evita caracteres especiales, acentos o símbolos. Si el error persiste, cierra sesión y vuelve a iniciar sesión, esto resuelve la mayoría de problemas técnicos.'
            ],
        ];
    }

    public function render()
    {
        return view('livewire.landing.faq-section');
    }
}

