@extends('emails.layouts.mytaxeu')

@section('subject', '📁 Archivo Subido Correctamente - En Cola de Procesamiento')

@section('content')
    <h1 class="email-title">¡Archivo Subido con Éxito!</h1>

    <p class="email-text">
        Hola {{ $user->name }},
    </p>

    <p class="email-text">
        Tu archivo CSV de Amazon ha sido subido correctamente y está siendo preparado para su procesamiento.
        Te notificaremos cuando inicie el procesamiento y cuando esté completado.
    </p>

    @component('emails.components.card', ['type' => 'success', 'title' => 'Detalles del Archivo Subido'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 35%;">Nombre del archivo:</td>
                <td style="padding: 8px 0; font-weight: 600;" class="allow-break">{{ $file['name'] ?? 'archivo.csv' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tamaño:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $file['size_formatted'] ?? $file['size'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Filas detectadas:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($file['rows'] ?? 0) }} registros</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Fecha de subida:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ isset($upload['created_at']) ? \Carbon\Carbon::parse($upload['created_at'])->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">ID de procesamiento:</td>
                <td style="padding: 8px 0; font-weight: 600; font-family: monospace; font-size: 14px;">
                    #{{ $upload['id'] ?? 'N/A' }}
                </td>
            </tr>
        </table>
    @endcomponent

    @if(isset($processing['estimated_time']) && $processing['estimated_time'])
        @component('emails.components.alert', ['type' => 'info', 'title' => '⏱️ Tiempo Estimado de Procesamiento'])
            Tu archivo será procesado en aproximadamente <strong>{{ $processing['estimated_time'] }}</strong>.
            Te enviaremos un email cuando el procesamiento haya terminado.
        @endcomponent
    @endif

    @component('emails.components.metrics', ['metrics' => [
        'Posición en Cola' => $processing['queue_position'] ?? 'En proceso',
        'Créditos Utilizados' => $processing['credits_used'] ?? 10,
        'Créditos Restantes' => $processing['credits_remaining'] ?? 'N/A'
    ]])
    @endcomponent

    @component('emails.components.card', ['type' => 'highlight', 'title' => '🔄 Próximos Pasos'])
        <ol style="margin: 0; padding-left: 20px; color: #1e40af;">
            <li style="margin-bottom: 8px;"><strong>Validación:</strong> Verificamos la estructura del archivo CSV</li>
            <li style="margin-bottom: 8px;"><strong>Procesamiento:</strong> Transformamos y clasificamos los datos</li>
            <li style="margin-bottom: 8px;"><strong>Validación IVA:</strong> Verificamos números de IVA europeos automáticamente</li>
            <li style="margin-bottom: 8px;"><strong>Generación:</strong> Creamos los informes para modelos 349 y 369</li>
            <li><strong>Descarga:</strong> Te enviamos el link para descargar los resultados</li>
        </ol>
    @endcomponent

    @component('emails.components.button', ['url' => config('app.url') . '/uploads/' . ($upload['id'] ?? ''), 'type' => 'primary'])
        🔍 Ver Estado del Procesamiento
    @endcomponent

    @if(($file['rows'] ?? 0) > 100 && !$user->subscribed())
        @component('emails.components.alert', ['type' => 'warning', 'title' => '⚠️ Limitación del Plan Gratuito'])
            Tu archivo contiene {{ number_format($file['rows']) }} filas, pero el plan gratuito está limitado a 100 filas.
            Solo se procesarán las primeras 100 filas.

            <div style="margin-top: 12px;">
                <a href="{{ config('app.url') }}/billing" style="color: #1e40af; text-decoration: none; font-weight: 600;">
                    ⬆️ Actualizar a Plan de Pago
                </a>
            </div>
        @endcomponent
    @endif

    <div style="margin: 32px 0 16px 0;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            Mientras Esperas...
        </h3>
        <p class="email-text">
            Puedes seguir subiendo más archivos o revisar tus procesamiento anteriores desde tu dashboard.
        </p>

        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;">
            <tr>
                <td style="padding-right: 16px;">
                    <a href="{{ config('app.url') }}/uploads/new"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        📁 Subir Otro Archivo
                    </a>
                </td>
                <td>
                    <a href="{{ config('app.url') }}/uploads"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        📊 Ver Historial
                    </a>
                </td>
            </tr>
        </table>
    </div>

    @if(isset($processing['tips']) && is_array($processing['tips']) && count($processing['tips']) > 0)
        @component('emails.components.card', ['type' => 'default', 'title' => '💡 Consejos para Optimizar tus Archivos'])
            <ul style="margin: 0; padding-left: 20px; color: #374151;">
                @foreach($processing['tips'] as $tip)
                    <li style="margin-bottom: 6px;">{{ $tip }}</li>
                @endforeach
            </ul>
        @endcomponent
    @endif

    <p class="email-text">
        Si tienes alguna pregunta sobre el procesamiento de archivos o necesitas ayuda,
        no dudes en <a href="mailto:{{ config('emails.content.support_email', 'soporte@mytaxeu.com') }}"
        style="color: #1e40af; text-decoration: none;">contactar con nuestro equipo de soporte</a>.
    </p>

    <p class="email-text-small">
        <strong>Nota:</strong> Los archivos subidos son procesados de forma segura y eliminados después de 30 días por motivos de privacidad.
        Asegúrate de descargar tus resultados a tiempo.
    </p>
@endsection


