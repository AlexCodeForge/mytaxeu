@extends('emails.layouts.mytaxeu')

@section('subject', 'Procesamiento Completado Exitosamente - MyTaxEU')

@section('content')
    <h1 class="email-title">🎉 ¡Procesamiento Completado!</h1>
    <p class="email-text">Su archivo CSV ha sido procesado exitosamente y está listo para descargar.</p>

    @component('emails.components.card', ['type' => 'success', 'title' => '✅ Resumen del Procesamiento'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 40%;">Archivo original:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->original_name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Filas procesadas:</td>
                <td style="padding: 8px 0; font-weight: 600; color: #10b981;">{{ number_format($upload->rows_count ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tamaño:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->formatted_size }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Créditos utilizados:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->credits_consumed ?? $upload->credits_required ?? 0 }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Completado el:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->processed_at?->format('d/m/Y H:i:s') ?? now()->format('d/m/Y H:i:s') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tiempo de procesamiento:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $processingTime ?? '2-3 minutos' }}</td>
            </tr>
        </table>
    @endcomponent

    @if(isset($qualityScore))
    @component('emails.components.alert', ['type' => ($qualityScore >= 90 ? 'success' : ($qualityScore >= 70 ? 'warning' : 'error')), 'title' => '📊 Puntuación de Calidad'])
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-size: 24px; font-weight: 900; color: {{ $qualityScore >= 90 ? '#10b981' : ($qualityScore >= 70 ? '#fbbf24' : '#ef4444') }};">
                {{ $qualityScore }}%
            </div>
            <div style="color: #6b7280;">
                @if($qualityScore >= 90)
                    ¡Excelente! Sus datos tienen muy alta calidad.
                @elseif($qualityScore >= 70)
                    Buena calidad. Algunas mejoras menores recomendadas.
                @else
                    Datos procesados con advertencias. Revise el reporte.
                @endif
            </div>
        </div>
    @endcomponent
    @endif

    @component('emails.components.alert', ['type' => 'info', 'title' => '📋 Resultados Disponibles'])
        <div style="color: #6b7280;">
            Su archivo ha sido procesado y transformado según los estándares fiscales españoles:<br>
            • <strong>Datos validados:</strong> Verificación de formato y consistencia<br>
            • <strong>Campos calculados:</strong> Totales, impuestos y referencias<br>
            • <strong>Formato estandarizado:</strong> Compatible con AEAT y gestiones fiscales<br>
            • <strong>Reporte de calidad:</strong> Estadísticas y posibles mejoras
        </div>
    @endcomponent

    <div style="text-align: center; margin: 32px 0;">
        @component('emails.components.button', ['url' => route('dashboard'), 'type' => 'primary'])
            📥 Descargar Resultados
        @endcomponent

        @component('emails.components.button', ['url' => route('dashboard'), 'type' => 'secondary'])
            👁️ Ver Detalles
        @endcomponent
    </div>

    <p class="email-text-small">
        <strong>📊 Estadística:</strong> Ha procesado un total de {{ $totalProcessedFiles ?? 'varios' }} archivos con MyTaxEU. ¡Gracias por confiar en nosotros!
    </p>
@endsection
