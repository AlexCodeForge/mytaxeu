@extends('emails.layouts.mytaxeu')

@section('subject', 'Archivo en Cola de Procesamiento - MyTaxEU')

@section('content')
    <h1 class="email-title">🕒 Archivo en Cola de Procesamiento</h1>
    <p class="email-text">Su archivo ha sido recibido exitosamente y añadido a la cola de procesamiento.</p>

    @component('emails.components.card', ['type' => 'highlight', 'title' => '📄 Detalles del Archivo'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 40%;">Nombre del archivo:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->original_name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tamaño:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->formatted_size }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Filas estimadas:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($upload->csv_line_count ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Recibido:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Posición en cola:</td>
                <td style="padding: 8px 0; font-weight: 600; color: #fbbf24;">#{{ $queuePosition ?? 'Calculando' }}</td>
            </tr>
        </table>
    @endcomponent

    @component('emails.components.alert', ['type' => 'info', 'title' => '⏱️ Tiempo Estimado de Espera'])
        <div style="font-size: 16px; font-weight: 600; margin-bottom: 8px; color: #1e40af;">
            {{ $estimatedWaitTime ?? '5-15 minutos' }}
        </div>
        <div style="color: #6b7280;">
            Su archivo será procesado en orden de llegada. Le enviaremos notificaciones cuando comience el procesamiento y cuando esté completado.
        </div>
    @endcomponent

    <div style="text-align: center; margin: 32px 0;">
        @component('emails.components.button', ['url' => route('dashboard'), 'type' => 'primary'])
            📊 Ver Estado en Dashboard
        @endcomponent
    </div>

    <p class="email-text-small">
        <strong>💡 Consejo:</strong> Los archivos más pequeños se procesan más rápido. Para archivos grandes, recomendamos dividirlos en lotes más pequeños.
    </p>
@endsection
