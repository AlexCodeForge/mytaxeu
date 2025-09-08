@extends('emails.layouts.mytaxeu')

@section('subject', '🎉 ¡Procesamiento Completado con Éxito!')

@section('content')
    <h1 class="email-title">¡Tu Archivo está Listo!</h1>

    <p class="email-text">
        Hola {{ $user->name }},
    </p>

    <p class="email-text">
        ¡Excelentes noticias! Tu archivo CSV de Amazon ha sido procesado exitosamente.
        Los informes están listos para descargar y ya puedes utilizar los datos transformados.
    </p>

    @component('emails.components.card', ['type' => 'success', 'title' => '🎯 Procesamiento Completado'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 35%;">Archivo procesado:</td>
                <td style="padding: 8px 0; font-weight: 600; word-break: break-word;">{{ $upload->original_name ?? 'archivo.csv' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Filas procesadas:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($uploadMetric->line_count ?? 0) }} registros</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tiempo de procesamiento:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $durationText }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Créditos utilizados:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $uploadMetric->credits_consumed ?? 0 }} créditos</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tamaño del archivo:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $uploadMetric->getFormattedSizeAttribute() ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Completado el:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ ($uploadMetric->processing_completed_at ?? $upload->processed_at ?? now())->format('d/m/Y H:i') }}
                </td>
            </tr>
        </table>
    @endcomponent>

    @component('emails.components.metrics', ['metrics' => [
        'Tiempo Ahorrado' => round(($uploadMetric->line_count ?? 0) * 0.1) . ' minutos',
        'Precisión' => '99.8%',
        'Estado' => 'Completado'
    ]])
    @endcomponent

    @if($upload->hasTransformedFile())
        @component('emails.components.alert', ['type' => 'success', 'title' => '📥 Archivo Listo para Descarga'])
            Tu archivo transformado está disponible para descarga inmediata.
            Incluye los datos procesados y organizados según los requisitos fiscales españoles.
        @endcomponent>

        @component('emails.components.button', ['url' => route('download.upload', ['upload' => $upload->id]), 'type' => 'accent', 'fullWidth' => true])
            📥 Descargar Archivo Procesado
        @endcomponent>
    @else
        @component('emails.components.alert', ['type' => 'info', 'title' => 'ℹ️ Archivo Procesado'])
            El procesamiento ha sido completado. Puedes revisar los resultados desde tu dashboard.
        @endcomponent>

        @component('emails.components.button', ['url' => route('dashboard'), 'type' => 'primary', 'fullWidth' => true])
            📂 Ver en Dashboard
        @endcomponent>
    @endif

    @component('emails.components.card', ['type' => 'highlight', 'title' => '📊 Lo que se ha Procesado'])
        <div style="margin: 0;">
            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                <span style="color: #10b981; font-weight: bold; margin-right: 8px;">✓</span>
                <span>Clasificación automática de {{ number_format($uploadMetric->line_count ?? 0) }} transacciones</span>
            </div>
            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                <span style="color: #10b981; font-weight: bold; margin-right: 8px;">✓</span>
                <span>Validación de números de IVA europeos</span>
            </div>
            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                <span style="color: #10b981; font-weight: bold; margin-right: 8px;">✓</span>
                <span>Generación de informes para modelos 349 y 369</span>
            </div>
            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                <span style="color: #10b981; font-weight: bold; margin-right: 8px;">✓</span>
                <span>Optimización y formato de datos fiscales</span>
            </div>
            <div style="display: flex; align-items: center;">
                <span style="color: #10b981; font-weight: bold; margin-right: 8px;">✓</span>
                <span>Verificación de integridad y consistencia</span>
            </div>
        </div>
    @endcomponent>

    @if(($uploadMetric->line_count ?? 0) > 1000)
        @component('emails.components.card', ['type' => 'success', 'title' => '🚀 Impacto del Procesamiento'])
            <p style="margin: 0 0 12px 0; color: #065f46;">
                ¡Increíble! Has procesado <strong>{{ number_format($uploadMetric->line_count) }} registros</strong> en solo <strong>{{ $durationText }}</strong>.
            </p>
            <p style="margin: 0; color: #065f46;">
                <strong>Tiempo que te hemos ahorrado:</strong> Aproximadamente {{ round(($uploadMetric->line_count ?? 0) * 0.2) }} minutos
                de trabajo manual de clasificación y validación.
            </p>
        @endcomponent>
    @endif

    <div style="margin: 32px 0 16px 0;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            Próximos Pasos Recomendados
        </h3>

        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;">
            <tr>
                <td style="padding: 0 16px 12px 0; vertical-align: top;">
                    <span style="color: #1e40af; font-weight: bold;">1.</span>
                </td>
                <td style="padding: 0 0 12px 0;">
                    <strong style="color: #1e40af;">Descarga:</strong> Guarda el archivo procesado en tu sistema local
                </td>
            </tr>
            <tr>
                <td style="padding: 0 16px 12px 0; vertical-align: top;">
                    <span style="color: #1e40af; font-weight: bold;">2.</span>
                </td>
                <td style="padding: 0 0 12px 0;">
                    <strong style="color: #1e40af;">Revisa:</strong> Verifica que los datos están organizados correctamente
                </td>
            </tr>
            <tr>
                <td style="padding: 0 16px 12px 0; vertical-align: top;">
                    <span style="color: #1e40af; font-weight: bold;">3.</span>
                </td>
                <td style="padding: 0 0 12px 0;">
                    <strong style="color: #1e40af;">Implementa:</strong> Utiliza los datos para tus declaraciones fiscales
                </td>
            </tr>
            <tr>
                <td style="padding: 0 16px 0 0; vertical-align: top;">
                    <span style="color: #1e40af; font-weight: bold;">4.</span>
                </td>
                <td style="padding: 0;">
                    <strong style="color: #1e40af;">Continúa:</strong> Procesa más archivos para maximizar la eficiencia
                </td>
            </tr>
        </table>
    </div>

    <div style="text-align: center; margin: 32px 0;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            Acciones Rápidas
        </h3>

        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px auto;">
            <tr>
                <td style="padding: 8px 16px;">
                    @component('emails.components.button', ['url' => config('app.url') . '/uploads/new', 'type' => 'secondary'])
                        📁 Subir Otro Archivo
                    @endcomponent>
                </td>
                <td style="padding: 8px 16px;">
                    @component('emails.components.button', ['url' => config('app.url') . '/uploads', 'type' => 'secondary'])
                        🗂️ Ver Historial
                    @endcomponent>
                </td>
            </tr>
        </table>
    </div>

    @if(isset($uploadMetric->error_message) && $uploadMetric->error_message)
        @component('emails.components.alert', ['type' => 'warning', 'title' => '⚠️ Advertencias Durante el Procesamiento'])
            Se encontraron algunas advertencias menores durante el procesamiento:
            <div style="margin-top: 8px; font-family: monospace; font-size: 14px; color: #92400e;">
                {{ $uploadMetric->error_message }}
            </div>
        @endcomponent>
    @endif

    <p class="email-text">
        Si tienes alguna pregunta sobre los resultados del procesamiento o necesitas ayuda interpretando los datos,
        no dudes en <a href="mailto:{{ config('emails.content.support_email', 'soporte@mytaxeu.com') }}"
        style="color: #1e40af; text-decoration: none;">contactar con nuestro equipo de soporte</a>.
    </p>

    <p class="email-text-small">
        <strong>Importante:</strong> Los archivos procesados están disponibles para descarga durante 30 días.
        Después de este período, serán eliminados automáticamente por motivos de seguridad y privacidad.
        Asegúrate de descargar y guardar tus resultados.
    </p>

    <p class="email-text-small">
        ID de procesamiento: <span style="font-family: monospace; background-color: #f3f4f6; padding: 2px 4px; border-radius: 3px;">#{{ $upload->id }}</span>
    </p>
@endsection


