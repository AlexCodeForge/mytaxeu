@extends('emails.layouts.mytaxeu')

@section('subject', 'Archivo Recibido Exitosamente - MyTaxEU')

@section('content')
    <h1 class="email-title">📥 ¡Archivo Recibido con Éxito!</h1>
    <p class="email-text">Hemos recibido su archivo exitosamente y está siendo preparado para procesamiento.</p>

    @component('emails.components.card', ['type' => 'success', 'title' => '📄 Información del Archivo'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 40%;">Nombre original:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->original_name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tamaño del archivo:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->formatted_size }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Líneas detectadas:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($upload->csv_line_count ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Créditos estimados:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $upload->credits_required ?? 'Calculando...' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">ID de referencia:</td>
                <td style="padding: 8px 0; font-weight: 600; color: #1e40af;">#{{ $upload->id }}</td>
            </tr>
        </table>
    @endcomponent

    @component('emails.components.alert', ['type' => 'info', 'title' => '📋 Próximos Pasos'])
        <div style="color: #6b7280;">
            <strong>1. ✅ Archivo Recibido</strong> - Su archivo ha sido cargado exitosamente<br>
            <strong>2. ⏳ Validación y Cola</strong> - Verificando formato y añadiendo a cola<br>
            <strong>3. ⚙️ Procesamiento</strong> - Análisis y transformación de datos fiscales<br>
            <strong>4. 📊 Resultados Listos</strong> - Archivo procesado y disponible para descarga
        </div>
    @endcomponent

    <div style="text-align: center; margin: 32px 0;">
        @component('emails.components.button', ['url' => route('dashboard'), 'type' => 'primary'])
            👁️ Seguir Progreso
        @endcomponent
    </div>

    <p class="email-text-small">
        <strong>📧 Notificaciones:</strong> Le mantendremos informado sobre cada paso del proceso por email.
    </p>
@endsection
