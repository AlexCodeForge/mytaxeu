@extends('emails.layouts.mytaxeu')

@section('subject', '💰 Nueva Venta Realizada - MyTaxEU')

@section('content')
    <h1 class="email-title">🎉 ¡Nueva Venta Confirmada!</h1>

    <p class="email-text">
        Se ha realizado una nueva venta en MyTaxEU. Aquí tienes los detalles:
    </p>

    @component('emails.components.card', ['type' => 'success', 'title' => '👤 Información del Cliente'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 30%;">Cliente:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $customer['name'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Email:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $customer['email'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">ID de cliente:</td>
                <td style="padding: 8px 0; font-weight: 600; font-family: monospace; font-size: 14px;">
                    {{ $customer['id'] ?? 'N/A' }}
                </td>
            </tr>
            @if(isset($customer['country']))
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">País:</td>
                    <td style="padding: 8px 0; font-weight: 600;">{{ $customer['country'] }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Cliente desde:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ isset($customer['created_at']) ? \Carbon\Carbon::parse($customer['created_at'])->format('d/m/Y') : 'N/A' }}
                </td>
            </tr>
        </table>
    @endcomponent>

    @component('emails.components.card', ['type' => 'highlight', 'title' => '💳 Detalles de la Venta'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 30%;">Plan adquirido:</td>
                <td style="padding: 8px 0; font-weight: 600; color: #1e40af;">{{ $sale['plan_name'] ?? 'Plan MyTaxEU' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Importe:</td>
                <td style="padding: 8px 0; font-weight: 600; font-size: 18px; color: #10b981;">
                    {{ number_format($sale['amount'] ?? 0, 2) }} {{ strtoupper($sale['currency'] ?? 'EUR') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Fecha de venta:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ isset($sale['created_at']) ? \Carbon\Carbon::parse($sale['created_at'])->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">ID de transacción:</td>
                <td style="padding: 8px 0; font-weight: 600; font-family: monospace; font-size: 14px;">
                    {{ $sale['transaction_id'] ?? $sale['stripe_payment_intent_id'] ?? 'N/A' }}
                </td>
            </tr>
            @if(isset($sale['billing_cycle']))
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Ciclo de facturación:</td>
                    <td style="padding: 8px 0; font-weight: 600;">{{ $sale['billing_cycle'] }}</td>
                </tr>
            @endif
        </table>
    @endcomponent>

    @component('emails.components.metrics', ['metrics' => [
        'Ventas Hoy' => $revenue['today_sales'] ?? 1,
        'Ingresos Hoy' => '€' . number_format($revenue['today_revenue'] ?? $sale['amount'] ?? 0, 0),
        'Ventas del Mes' => $revenue['month_sales'] ?? 1,
        'Ingresos del Mes' => '€' . number_format($revenue['month_revenue'] ?? $sale['amount'] ?? 0, 0)
    ]])
    @endcomponent>

    @if(isset($sale['is_first_purchase']) && $sale['is_first_purchase'])
        @component('emails.components.alert', ['type' => 'success', 'title' => '🎯 ¡Nuevo Cliente!'])
            Esta es la primera compra de este cliente. ¡Bienvenido a la familia MyTaxEU!
        @endcomponent>
    @endif

    @if(isset($sale['discount_applied']) && $sale['discount_applied'])
        @component('emails.components.alert', ['type' => 'info', 'title' => '🎟️ Descuento Aplicado'])
            Se aplicó un descuento de <strong>{{ number_format($sale['discount_amount'] ?? 0, 2) }} {{ strtoupper($sale['currency'] ?? 'EUR') }}</strong>
            @if(isset($sale['coupon_code']))
                con el cupón <strong>{{ $sale['coupon_code'] }}</strong>.
            @endif
        @endcomponent>
    @endif

    @component('emails.components.card', ['type' => 'default', 'title' => '📊 Resumen del Rendimiento'])
        <div style="margin: 0;">
            @if(isset($revenue['growth_percentage']))
                <div style="margin-bottom: 12px;">
                    <strong style="color: #1e40af;">Crecimiento vs mes anterior:</strong>
                    <span style="color: {{ ($revenue['growth_percentage'] ?? 0) >= 0 ? '#10b981' : '#ef4444' }}; font-weight: 600;">
                        {{ number_format($revenue['growth_percentage'] ?? 0, 1) }}%
                        {{ ($revenue['growth_percentage'] ?? 0) >= 0 ? '📈' : '📉' }}
                    </span>
                </div>
            @endif

            @if(isset($revenue['avg_transaction_value']))
                <div style="margin-bottom: 12px;">
                    <strong style="color: #1e40af;">Valor promedio por transacción:</strong>
                    <span style="font-weight: 600;">{{ number_format($revenue['avg_transaction_value'], 2) }} EUR</span>
                </div>
            @endif

            @if(isset($revenue['plan_distribution']))
                <div>
                    <strong style="color: #1e40af;">Distribución de planes (este mes):</strong>
                    <ul style="margin: 8px 0 0 20px; padding: 0;">
                        @foreach($revenue['plan_distribution'] as $plan => $count)
                            <li style="margin-bottom: 4px;">{{ $plan }}: {{ $count }} ventas</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endcomponent>

    <div style="text-align: center; margin: 32px 0;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            Acciones Rápidas
        </h3>

        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px auto;">
            <tr>
                <td style="padding: 8px 16px;">
                    @component('emails.components.button', ['url' => config('app.url') . '/admin/users/' . ($customer['id'] ?? ''), 'type' => 'primary'])
                        👤 Ver Perfil del Cliente
                    @endcomponent>
                </td>
                <td style="padding: 8px 16px;">
                    @component('emails.components.button', ['url' => config('app.url') . '/admin/sales', 'type' => 'secondary'])
                        📊 Dashboard de Ventas
                    @endcomponent>
                </td>
            </tr>
        </table>
    </div>

    @if(isset($revenue['milestone_reached']) && $revenue['milestone_reached'])
        @component('emails.components.alert', ['type' => 'success', 'title' => '🏆 ¡Hito Alcanzado!'])
            {{ $revenue['milestone_message'] ?? '¡Has alcanzado un nuevo hito de ventas!' }}
        @endcomponent>
    @endif

    <div style="margin: 32px 0 16px 0;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            Recordatorios
        </h3>
        <ul style="margin: 0; padding-left: 20px; color: #374151;">
            <li style="margin-bottom: 8px;">Verifica que el cliente recibió su email de confirmación</li>
            <li style="margin-bottom: 8px;">Los créditos se asignan automáticamente tras el pago</li>
            <li style="margin-bottom: 8px;">El cliente puede empezar a usar el servicio inmediatamente</li>
            <li>Considera enviar un email de bienvenida personalizado para nuevos clientes</li>
        </ul>
    </div>

    <p class="email-text-small">
        <strong>Nota:</strong> Esta notificación se genera automáticamente cuando se confirma un pago exitoso.
        Los datos se sincronizan en tiempo real con Stripe.
    </p>

    <p class="email-text-small">
        Timestamp: {{ now()->format('Y-m-d H:i:s') }} |
        Notification ID: {{ uniqid('sale_') }}
    </p>
@endsection


