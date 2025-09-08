@extends('emails.layouts.mytaxeu')

@section('subject', '🎉 Pago Confirmado - Créditos Añadidos a tu Cuenta')

@section('content')
    <h1 class="email-title">¡Pago Confirmado con Éxito!</h1>

    <p class="email-text">
        Hola {{ $user->name }},
    </p>

    <p class="email-text">
        Hemos recibido correctamente tu pago y tus créditos han sido añadidos a tu cuenta inmediatamente.
        Ya puedes empezar a procesar tus archivos de Amazon.
    </p>

    @component('emails.components.card', ['type' => 'success', 'title' => 'Detalles del Pago'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 40%;">Plan:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $subscription['plan_name'] ?? 'Plan MyTaxEU' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Importe:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ number_format($payment['amount'] ?? 0, 2) }} {{ strtoupper($payment['currency'] ?? 'EUR') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Fecha de pago:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ isset($payment['paid_at']) ? \Carbon\Carbon::parse($payment['paid_at'])->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">ID de transacción:</td>
                <td style="padding: 8px 0; font-weight: 600; font-family: monospace; font-size: 14px;">
                    {{ $payment['transaction_id'] ?? $payment['stripe_payment_intent_id'] ?? 'N/A' }}
                </td>
            </tr>
        </table>
    @endcomponent

    @component('emails.components.metrics', ['metrics' => [
        'Créditos Añadidos' => number_format($credits['amount'] ?? 0),
        'Créditos Totales' => number_format($credits['total_balance'] ?? 0),
        'Archivos Disponibles' => number_format($credits['files_processable'] ?? 0)
    ]])
    @endcomponent

    @component('emails.components.alert', ['type' => 'info', 'title' => '💡 ¿Qué puedes hacer ahora?'])
        <ul style="margin: 8px 0 0 20px; padding: 0; color: #1e40af;">
            <li style="margin-bottom: 4px;">✅ Subir y procesar archivos CSV de Amazon</li>
            <li style="margin-bottom: 4px;">📊 Generar informes automáticos para modelos 349 y 369</li>
            <li style="margin-bottom: 4px;">🔍 Validar números de IVA automáticamente</li>
            <li style="margin-bottom: 4px;">📧 Recibir notificaciones de estado en tiempo real</li>
        </ul>
    @endcomponent

    @component('emails.components.button', ['url' => config('app.url') . '/dashboard', 'type' => 'accent', 'fullWidth' => true])
        🚀 Ir al Dashboard y Empezar
    @endcomponent

    <div style="margin: 32px 0 16px 0; text-align: center;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            Próxima Renovación
        </h3>
        <p class="email-text">
            Tu suscripción se renovará automáticamente el
            <strong>{{ isset($subscription['next_billing_date']) ? \Carbon\Carbon::parse($subscription['next_billing_date'])->format('d/m/Y') : 'fecha por determinar' }}</strong>.
            Podrás gestionar tu suscripción desde tu dashboard en cualquier momento.
        </p>
    </div>

    @if(isset($subscription['promo_code']) && $subscription['promo_code'])
        @component('emails.components.card', ['type' => 'highlight', 'title' => '🎁 Código Promocional Aplicado'])
            Has utilizado el código promocional <strong>{{ $subscription['promo_code'] }}</strong>
            y has ahorrado {{ number_format($subscription['discount_amount'] ?? 0, 2) }} {{ strtoupper($payment['currency'] ?? 'EUR') }}.
        @endcomponent
    @endif

    <p class="email-text">
        Si tienes alguna pregunta sobre tu suscripción o necesitas ayuda con el procesamiento de archivos,
        no dudes en <a href="mailto:{{ config('emails.content.support_email', 'soporte@mytaxeu.com') }}"
        style="color: #1e40af; text-decoration: none;">contactar con nuestro equipo de soporte</a>.
    </p>

    <p class="email-text-small">
        <strong>Nota:</strong> Este email confirma el pago exitoso de tu suscripción.
        Conserva este email como comprobante de tu transacción.
    </p>
@endsection


