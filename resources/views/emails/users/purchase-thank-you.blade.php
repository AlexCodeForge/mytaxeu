@extends('emails.layouts.mytaxeu')

@section('subject', '🎉 ¡Gracias por tu compra! - Bienvenido a MyTaxEU')

@section('content')
    <h1 class="email-title">¡Gracias por tu Compra!</h1>

    <p class="email-text">
        Hola {{ $customer['name'] ?? 'Usuario' }},
    </p>

    <p class="email-text">
        ¡Muchísimas gracias por confiar en <strong>MyTaxEU</strong>! Estamos emocionados de tenerte como parte de nuestra comunidad.
        Tu compra ha sido procesada exitosamente y ya tienes acceso completo a todas nuestras funcionalidades.
    </p>

    @component('emails.components.card', ['type' => 'success', 'title' => 'Detalles de tu Compra'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 40%;">Plan:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $subscription['plan_name'] ?? 'Plan Premium' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Importe:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $purchase['amount_formatted'] ?? '€29.99' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Fecha de compra:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $purchase['date'] ?? now()->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Método de pago:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $purchase['payment_method'] ?? 'Tarjeta de crédito' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">ID de transacción:</td>
                <td style="padding: 8px 0; font-weight: 600; font-family: monospace; font-size: 14px;">
                    {{ $purchase['transaction_id'] ?? 'N/A' }}
                </td>
            </tr>
        </table>
    @endcomponent

    @if($credits['credits_added'] ?? false)
        @component('emails.components.metrics', ['metrics' => [
            'Créditos Añadidos' => number_format($credits['credits_added'] ?? 0),
            'Créditos Totales' => number_format($credits['total_credits'] ?? 0),
            'Créditos Disponibles' => number_format($credits['credits_remaining'] ?? 0)
        ]])
        @endcomponent
    @endif

    @component('emails.components.alert', ['type' => 'info', 'title' => '🚀 ¿Qué puedes hacer ahora?'])
        <ul style="margin: 8px 0 0 20px; padding: 0; color: #1e40af;">
            <li style="margin-bottom: 6px;">✅ Subir y procesar tus archivos CSV de Amazon</li>
            <li style="margin-bottom: 6px;">📊 Generar reportes automáticos para modelos 349 y 369</li>
            <li style="margin-bottom: 6px;">🇪🇺 Acceder a validaciones de IVA europeas</li>
            <li style="margin-bottom: 6px;">📈 Consultar tu historial de procesamientos</li>
            <li>💼 Gestionar tu cuenta y configuraciones</li>
        </ul>
    @endcomponent

    @component('emails.components.button', ['url' => config('app.url') . '/dashboard', 'type' => 'accent', 'fullWidth' => true])
        🏠 Acceder a tu Panel de Control
    @endcomponent

    @if($subscription['next_billing_date'] ?? false)
        <div style="margin: 32px 0 16px 0; text-align: center;">
            <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
                Información de Suscripción
            </h3>
            <p class="email-text">
                Tu suscripción <strong>{{ $subscription['plan_name'] ?? 'Premium' }}</strong> está activa.
                @if($subscription['next_billing_date'])
                    La próxima renovación será el <strong>{{ $subscription['next_billing_date'] }}</strong>.
                @endif
            </p>
        </div>
    @endif

    @component('emails.components.card', ['type' => 'highlight', 'title' => '💡 Primeros Pasos Recomendados'])
        <ol style="margin: 0; padding-left: 20px; color: #1e40af;">
            <li style="margin-bottom: 8px;"><strong>Explora tu dashboard:</strong> Familiarízate con las funcionalidades</li>
            <li style="margin-bottom: 8px;"><strong>Sube tu primer archivo:</strong> Procesa datos de Amazon fácilmente</li>
            <li style="margin-bottom: 8px;"><strong>Configura notificaciones:</strong> Mantente informado del progreso</li>
            <li><strong>Revisa los tutoriales:</strong> Optimiza tu flujo de trabajo</li>
        </ol>
    @endcomponent

    <div style="margin: 32px 0 16px 0;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            Enlaces Útiles
        </h3>
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;">
            <tr>
                <td style="padding-right: 20px; padding-bottom: 8px;">
                    <a href="{{ config('app.url') }}/uploads/new"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        📁 Subir Archivo
                    </a>
                </td>
                <td style="padding-bottom: 8px;">
                    <a href="{{ config('app.url') }}/uploads"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        📊 Ver Historial
                    </a>
                </td>
            </tr>
            <tr>
                <td style="padding-right: 20px;">
                    <a href="{{ config('app.url') }}/billing"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        💳 Gestionar Cuenta
                    </a>
                </td>
                <td>
                    <a href="{{ config('app.url') }}/help"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        ❓ Centro de Ayuda
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <p class="email-text">
        Si tienes alguna pregunta sobre tu compra o necesitas ayuda para empezar,
        no dudes en <a href="mailto:{{ config('emails.content.support_email', 'soporte@mytaxeu.com') }}"
        style="color: #1e40af; text-decoration: none;">contactar con nuestro equipo de soporte</a>.
        Estamos aquí para ayudarte a sacar el máximo provecho de MyTaxEU.
    </p>

    <p class="email-text">
        ¡Gracias de nuevo por elegirnos y bienvenido a la familia MyTaxEU!
    </p>

    <p class="email-text-small">
        <strong>Nota:</strong> Conserva este email como comprobante de tu transacción.
        Todos los datos se procesan de forma segura y siguiendo las mejores prácticas de privacidad.
    </p>
@endsection
