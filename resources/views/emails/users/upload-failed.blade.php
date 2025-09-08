@extends('emails.layouts.mytaxeu')

@section('subject', 'Error en Procesamiento de Archivo - MyTaxEU')

@section('content')
    <h1 class="email-title">⚠️ Error en el Procesamiento</h1>
    <p class="email-text">Hemos encontrado un problema al procesar su archivo. No se preocupe, nuestro equipo está aquí para ayudarle.</p>

    @component('emails.components.card', ['type' => 'error', 'title' => '❌ Detalles del Error'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 40%;">Archivo:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->original_name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tamaño:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->formatted_size }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Intentado el:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ now()->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">ID de referencia:</td>
                <td style="padding: 8px 0; font-weight: 600; color: #ef4444;">#{{ $upload->id }}</td>
            </tr>
        </table>

        @if($upload->failure_reason)
        <div style="margin-top: 16px; padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px;">
            <strong style="color: #dc2626;">Motivo del Error:</strong><br>
            <span style="color: #7f1d1d; font-family: monospace;">{{ $upload->failure_reason }}</span>
        </div>
        @endif
    @endcomponent

    @component('emails.components.alert', ['type' => 'warning', 'title' => '🔧 Posibles Soluciones'])
        <div style="color: #6b7280;">
            • <strong>Formato del archivo:</strong> Asegúrese de que el archivo sea un CSV válido con codificación UTF-8<br>
            • <strong>Estructura de datos:</strong> Verifique que las columnas tengan los nombres correctos<br>
            • <strong>Formato de fechas:</strong> Use el formato DD/MM/YYYY para todas las fechas<br>
            • <strong>Caracteres especiales:</strong> Evite caracteres especiales en los datos numéricos
        </div>
    @endcomponent

    <div style="text-align: center; margin: 32px 0;">
        @component('emails.components.button', ['url' => route('dashboard'), 'type' => 'primary'])
            🔄 Intentar de Nuevo
        @endcomponent

        @component('emails.components.button', ['url' => 'mailto:soporte@mytaxeu.com?subject=Error en archivo ' . $upload->original_name . '&body=ID de referencia: ' . $upload->id, 'type' => 'secondary'])
            📧 Contactar Soporte
        @endcomponent
    </div>

    <p class="email-text-small">
        <strong>📞 Soporte:</strong> Si continúa teniendo problemas, contáctenos en soporte@mytaxeu.com o al +34 900 123 456.
    </p>
@endsection
