@extends('emails.layouts.mytaxeu')

@section('subject', '⚠️ Error en el Procesamiento - Necesita Atención')

@section('content')
    <h1 class="email-title">Error en el Procesamiento</h1>

    <p class="email-text">
        Hola {{ $user->name }},
    </p>

    <p class="email-text">
        Lamentamos informarte que el procesamiento de tu archivo CSV de Amazon ha encontrado un error
        y no pudo completarse. No te preocupes, nuestro equipo técnico está aquí para ayudarte a resolverlo.
    </p>

    @component('emails.components.card', ['type' => 'error', 'title' => '❌ Detalles del Error'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 35%;">Archivo afectado:</td>
                <td style="padding: 8px 0; font-weight: 600; word-break: break-word;">{{ $upload->original_name ?? 'archivo.csv' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Error encontrado:</td>
                <td style="padding: 8px 0; font-weight: 600; color: #991b1b;">
                    {{ $errorMessage }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tiempo de procesamiento:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $durationText }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Filas procesadas antes del error:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($uploadMetric->line_count ?? 0) }} registros</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Fecha del error:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ ($uploadMetric->processing_completed_at ?? $upload->processed_at ?? now())->format('d/m/Y H:i') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">ID de referencia:</td>
                <td style="padding: 8px 0; font-weight: 600; font-family: monospace; font-size: 14px;">
                    #{{ $upload->id }}
                </td>
            </tr>
        </table>
    @endcomponent>

    @component('emails.components.alert', ['type' => 'warning', 'title' => '💡 No te preocupes'])
        Los errores de procesamiento son raros y generalmente tienen soluciones rápidas.
        La mayoría se resuelven ajustando el formato del archivo o corrigiendo datos específicos.
    @endcomponent>

    @component('emails.components.card', ['type' => 'default', 'title' => '🛠️ Soluciones Recomendadas'])
        <div style="margin: 0;">
            <div style="margin-bottom: 16px;">
                <h4 style="margin: 0 0 8px 0; color: #1e40af; font-size: 16px;">1. Verificación del Formato</h4>
                <ul style="margin: 0; padding-left: 20px; color: #374151;">
                    <li>Asegúrate de que el archivo es un CSV válido</li>
                    <li>Verifica que contiene la columna <strong>ACTIVITY_PERIOD</strong> requerida</li>
                    <li>Comprueba que las fechas están en formato correcto (YYYY-MM)</li>
                    <li>Revisa que no hay caracteres especiales en los nombres de columna</li>
                </ul>
            </div>

            <div style="margin-bottom: 16px;">
                <h4 style="margin: 0 0 8px 0; color: #1e40af; font-size: 16px;">2. Validación del Contenido</h4>
                <ul style="margin: 0; padding-left: 20px; color: #374151;">
                    <li>Límite máximo: 3 períodos distintos en ACTIVITY_PERIOD</li>
                    <li>Los números de IVA deben tener formato válido</li>
                    <li>Las cantidades deben ser numéricas</li>
                    <li>No debe haber filas completamente vacías</li>
                </ul>
            </div>

            <div style="margin-bottom: 16px;">
                <h4 style="margin: 0 0 8px 0; color: #1e40af; font-size: 16px;">3. Revisión Técnica</h4>
                <ul style="margin: 0; padding-left: 20px; color: #374151;">
                    <li>Verifica que el archivo no esté corrupto</li>
                    <li>Comprueba el tamaño (máximo recomendado: 10MB)</li>
                    <li>Asegúrate de que la codificación es UTF-8</li>
                    <li>Elimina caracteres de control o saltos de línea extraños</li>
                </ul>
            </div>
        </div>
    @endcomponent>

    @if(str_contains(strtolower($errorMessage), 'activity_period'))
        @component('emails.components.alert', ['type' => 'info', 'title' => '📅 Error Específico: ACTIVITY_PERIOD'])
            El error está relacionado con la columna ACTIVITY_PERIOD. Asegúrate de que:
            <ul style="margin: 8px 0 0 20px; padding: 0; color: #1e40af;">
                <li>La columna existe y se llama exactamente "ACTIVITY_PERIOD"</li>
                <li>Las fechas están en formato YYYY-MM (ej: 2024-01)</li>
                <li>No tienes más de 3 períodos diferentes</li>
                <li>No hay celdas vacías en esta columna</li>
            </ul>
        @endcomponent>
    @elseif(str_contains(strtolower($errorMessage), 'vat') || str_contains(strtolower($errorMessage), 'iva'))
        @component('emails.components.alert', ['type' => 'info', 'title' => '🏛️ Error Específico: Números de IVA'])
            El error está relacionado con la validación de números de IVA:
            <ul style="margin: 8px 0 0 20px; padding: 0; color: #1e40af;">
                <li>Verifica que los números de IVA tienen el formato correcto</li>
                <li>Incluye el código de país (ej: ES12345678Z)</li>
                <li>No uses espacios ni caracteres especiales</li>
                <li>Puedes dejar algunos campos vacíos si no aplican</li>
            </ul>
        @endcomponent>
    @endif

    <div style="text-align: center; margin: 32px 0;">
        @component('emails.components.button', ['url' => config('app.url') . '/uploads/new', 'type' => 'accent'])
            🔄 Intentar de Nuevo con Archivo Corregido
        @endcomponent>

        @component('emails.components.button', ['url' => config('app.url') . '/dashboard', 'type' => 'secondary'])
            📂 Ver Dashboard
        @endcomponent>
    </div>

    @component('emails.components.card', ['type' => 'highlight', 'title' => '🎯 ¿Necesitas Ayuda Personalizada?'])
        <p style="margin: 0 0 12px 0;">
            Nuestro equipo de soporte puede revisar tu archivo específico y ayudarte a identificar exactamente
            qué necesita ser corregido.
        </p>

        <div style="background-color: #f8fafc; padding: 12px; border-radius: 6px; margin: 12px 0;">
            <strong style="color: #1e40af;">Para contactar soporte, incluye:</strong>
            <ul style="margin: 8px 0 0 20px; padding: 0; color: #374151; font-size: 14px;">
                <li>ID de procesamiento: <span style="font-family: monospace; background-color: #e5e7eb; padding: 1px 4px; border-radius: 3px;">#{{ $upload->id }}</span></li>
                <li>Nombre del archivo: {{ $upload->original_name }}</li>
                <li>Descripción del problema que encontraste</li>
            </ul>
        </div>

        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;">
            <tr>
                <td style="padding-right: 16px;">
                    <a href="mailto:{{ config('emails.content.support_email', 'soporte@mytaxeu.com') }}?subject=Error en procesamiento #{{ $upload->id }}"
                       style="color: #1e40af; text-decoration: none; font-weight: 600;">
                        📧 Enviar Email a Soporte
                    </a>
                </td>
                <td>
                    <a href="{{ config('app.url') }}/help"
                       style="color: #1e40af; text-decoration: none; font-weight: 600;">
                        📚 Ver Guías de Ayuda
                    </a>
                </td>
            </tr>
        </table>
    @endcomponent>

    @component('emails.components.metrics', ['metrics' => [
        'Tiempo de Respuesta' => '< 2 horas',
        'Tasa de Resolución' => '98.5%',
        'Soporte' => '24/7'
    ]])
    @endcomponent

    <div style="margin: 32px 0 16px 0;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            Mientras Tanto...
        </h3>
        <p class="email-text">
            Puedes revisar otros archivos que hayas procesado anteriormente o subir un archivo diferente
            mientras resolvemos este problema.
        </p>

        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;">
            <tr>
                <td style="padding-right: 16px;">
                    <a href="{{ config('app.url') }}/uploads"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        🗂️ Ver Historial de Archivos
                    </a>
                </td>
                <td>
                    <a href="{{ config('app.url') }}/usage/dashboard"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        📈 Ver Estadísticas de Uso
                    </a>
                </td>
            </tr>
        </table>
    </div>

    @if(($uploadMetric->credits_consumed ?? 0) > 0)
        @component('emails.components.alert', ['type' => 'success', 'title' => '💳 Créditos Protegidos'])
            No te preocupes por los créditos. Como el procesamiento falló, los <strong>{{ $uploadMetric->credits_consumed }} créditos</strong>
            que se iban a consumir han sido devueltos automáticamente a tu cuenta.
        @endcomponent>
    @endif

    <p class="email-text">
        Lamentamos sinceramente las molestias causadas por este error. Estamos comprometidos a resolver
        este problema rápidamente y asegurar que tengas una experiencia excepcional con MyTaxEU.
    </p>

    <p class="email-text-small">
        <strong>Nota técnica:</strong> Este error ha sido registrado automáticamente en nuestros sistemas
        de monitoreo para análisis y prevención futura. Tu privacidad está protegida - solo almacenamos
        información técnica del error, no el contenido de tu archivo.
    </p>
@endsection


