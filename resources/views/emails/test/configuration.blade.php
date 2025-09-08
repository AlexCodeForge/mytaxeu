@extends('emails.layouts.mytaxeu')

@section('subject', 'MyTaxEU - Test de Configuración de Email')

@section('content')
    <h1 class="email-title">✅ Configuración de Email Exitosa</h1>

    <p class="email-text">
        Este email confirma que el sistema de emails de MyTaxEU está configurado correctamente y funcionando.
    </p>

    @component('emails.components.card', ['type' => 'success', 'title' => 'Detalles del Test'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 4px 0; color: #6b7280; font-weight: 500;">Hora del test:</td>
                <td style="padding: 4px 0; font-weight: 600;">{{ $test_time }}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0; color: #6b7280; font-weight: 500;">Aplicación:</td>
                <td style="padding: 4px 0; font-weight: 600;">{{ $app_name }}</td>
            </tr>
            <tr>
                <td style="padding: 4px 0; color: #6b7280; font-weight: 500;">Entorno:</td>
                <td style="padding: 4px 0; font-weight: 600;">{{ $environment }}</td>
            </tr>
        </table>
    @endcomponent

    @component('emails.components.alert', ['type' => 'info', 'title' => 'Sistema de Emails'])
        Todos los componentes del sistema de emails están funcionando correctamente:
        <ul style="margin: 8px 0 0 20px; padding: 0;">
            <li>✅ Plantillas personalizadas cargadas</li>
            <li>✅ Componentes de email funcionando</li>
            <li>✅ Configuración de cola aplicada</li>
            <li>✅ Sistema de logs activo</li>
        </ul>
    @endcomponent

    @component('emails.components.button', ['url' => config('app.url') . '/dashboard', 'type' => 'primary'])
        Ir al Dashboard
    @endcomponent

    <p class="email-text-small">
        Este es un email de prueba generado automáticamente. Si recibiste este email sin solicitarlo,
        puedes ignorarlo de forma segura.
    </p>
@endsection


