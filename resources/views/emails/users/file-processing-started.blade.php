@extends('emails.layouts.mytaxeu')

@section('subject', '🔄 Procesamiento Iniciado - Tu Archivo está siendo Transformado')

@section('content')
    <h1 class="email-title">¡Procesamiento en Marcha!</h1>

    <p class="email-text">
        Hola {{ $user->name }},
    </p>

    <p class="email-text">
        ¡Excelentes noticias! Tu archivo CSV de Amazon ha comenzado a procesarse.
        Nuestro sistema está trabajando para transformar tus datos y generar los informes automatizados.
    </p>

    @component('emails.components.card', ['type' => 'highlight', 'title' => '🔄 Estado del Procesamiento'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 35%;">Archivo:</td>
                <td style="padding: 8px 0; font-weight: 600;" class="allow-break">{{ $file['name'] ?? 'archivo.csv' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Inicio del procesamiento:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ isset($processing['started_at']) ? \Carbon\Carbon::parse($processing['started_at'])->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Filas a procesar:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($file['rows'] ?? 0) }} registros</td>
            </tr>
            @if(isset($processing['estimated_completion']))
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Finalización estimada:</td>
                    <td style="padding: 8px 0; font-weight: 600;">
                        {{ \Carbon\Carbon::parse($processing['estimated_completion'])->format('d/m/Y H:i') }}
                    </td>
                </tr>
            @endif
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">ID de procesamiento:</td>
                <td style="padding: 8px 0; font-weight: 600; font-family: monospace; font-size: 14px;">
                    #{{ $upload['id'] ?? 'N/A' }}
                </td>
            </tr>
        </table>
    @endcomponent

    @component('emails.components.card', ['type' => 'success', 'title' => '⚙️ Procesos Activos'])
        <div style="margin: 0;">
            <div style="display: flex; align-items: center; margin-bottom: 12px;">
                <span style="color: #10b981; font-weight: bold; margin-right: 8px;">✓</span>
                <span style="color: #065f46;">Validación de estructura completada</span>
            </div>
            <div style="display: flex; align-items: center; margin-bottom: 12px;">
                <span style="color: #fbbf24; font-weight: bold; margin-right: 8px;">●</span>
                <span style="color: #92400e;">Transformación de datos en progreso</span>
            </div>
            <div style="display: flex; align-items: center; margin-bottom: 12px;">
                <span style="color: #6b7280; font-weight: bold; margin-right: 8px;">○</span>
                <span style="color: #6b7280;">Validación de números IVA pendiente</span>
            </div>
            <div style="display: flex; align-items: center; margin-bottom: 12px;">
                <span style="color: #6b7280; font-weight: bold; margin-right: 8px;">○</span>
                <span style="color: #6b7280;">Generación de informes pendiente</span>
            </div>
            <div style="display: flex; align-items: center;">
                <span style="color: #6b7280; font-weight: bold; margin-right: 8px;">○</span>
                <span style="color: #6b7280;">Preparación para descarga pendiente</span>
            </div>
        </div>
    @endcomponent

    @if(isset($processing['estimated_duration']) && $processing['estimated_duration'])
        @component('emails.components.metrics', ['metrics' => [
            'Tiempo Estimado' => $processing['estimated_duration'],
            'Progreso Actual' => ($processing['progress_percentage'] ?? 15) . '%',
            'Tipo de Archivo' => $processing['file_type'] ?? 'Amazon CSV'
        ]])
        @endcomponent
    @endif

    @component('emails.components.alert', ['type' => 'info', 'title' => '🤖 Automatización en Acción'])
        Mientras tu archivo se procesa, nuestro sistema está:
        <ul style="margin: 8px 0 0 20px; padding: 0; color: #1e40af;">
            <li>📊 Clasificando automáticamente miles de transacciones</li>
            <li>🔍 Validando números de IVA con bases de datos europeas</li>
            <li>📋 Generando informes para modelos 349 y 369</li>
            <li>⚡ Optimizando los datos para máxima precisión</li>
        </ul>
    @endcomponent

    @component('emails.components.button', ['url' => config('app.url') . '/uploads/' . ($upload['id'] ?? ''), 'type' => 'primary'])
        📊 Ver Progreso en Tiempo Real
    @endcomponent

    <div style="margin: 32px 0 16px 0;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            ¿Qué Pasará Ahora?
        </h3>
        <p class="email-text">
            Te mantendremos informado del progreso. Recibirás otro email cuando el procesamiento
            esté completado con los enlaces de descarga de tus informes.
        </p>

        @if(isset($processing['notifications_enabled']) && $processing['notifications_enabled'])
            <p class="email-text-small">
                <strong>💬 Notificaciones activadas:</strong> También puedes seguir el progreso en tiempo real desde tu dashboard.
            </p>
        @endif
    </div>

    @if(isset($processing['processing_tips']) && is_array($processing['processing_tips']) && count($processing['processing_tips']) > 0)
        @component('emails.components.card', ['type' => 'default', 'title' => '💡 Datos Interesantes sobre tu Archivo'])
            <ul style="margin: 0; padding-left: 20px; color: #374151;">
                @foreach($processing['processing_tips'] as $tip)
                    <li style="margin-bottom: 6px;">{{ $tip }}</li>
                @endforeach
            </ul>
        @endcomponent
    @endif

    @if(($file['rows'] ?? 0) > 1000)
        @component('emails.components.card', ['type' => 'success', 'title' => '🚀 Archivo de Gran Volumen'])
            <p style="margin: 0; color: #065f46;">
                ¡Impresionante! Tu archivo contiene <strong>{{ number_format($file['rows']) }} registros</strong>.
                Procesar manualmente este volumen de datos te habría tomado aproximadamente
                <strong>{{ round(($file['rows'] / 100) * 2) }} horas</strong>.
                Con MyTaxEU, estará listo en minutos.
            </p>
        @endcomponent
    @endif

    <div style="margin: 32px 0 16px 0; text-align: center;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            Mientras Esperas...
        </h3>

        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px auto;">
            <tr>
                <td style="padding: 0 16px;">
                    <a href="{{ config('app.url') }}/uploads/new"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        📁 Subir Otro Archivo
                    </a>
                </td>
                <td style="padding: 0 16px;">
                    <a href="{{ config('app.url') }}/usage/dashboard"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        📈 Ver Estadísticas
                    </a>
                </td>
                <td style="padding: 0 16px;">
                    <a href="{{ config('app.url') }}/uploads"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        🗂️ Ver Historial
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <p class="email-text">
        Si tienes alguna pregunta o necesitas cancelar el procesamiento por algún motivo,
        puedes <a href="mailto:{{ config('emails.content.support_email', 'soporte@mytaxeu.com') }}"
        style="color: #1e40af; text-decoration: none;">contactarnos inmediatamente</a>.
    </p>

    <p class="email-text-small">
        <strong>Nota técnica:</strong> El procesamiento se realiza en servidores seguros con certificación ISO 27001.
        Tus datos están protegidos durante todo el proceso y se eliminan automáticamente después de la descarga.
    </p>
@endsection


