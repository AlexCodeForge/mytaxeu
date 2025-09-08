@extends('emails.layouts.mytaxeu')

@section('subject')
    @if($daysUntilRenewal === 1)
        ⚠️ Tu suscripción se renueva mañana
    @else
        🔔 Tu suscripción se renueva en {{ $daysUntilRenewal }} días
    @endif
@endsection

@section('content')
    <h1 class="email-title">
        @if($daysUntilRenewal === 1)
            Tu Suscripción se Renueva Mañana
        @else
            Recordatorio de Renovación
        @endif
    </h1>

    <p class="email-text">
        Hola {{ $user->name }},
    </p>

    <p class="email-text">
        @if($daysUntilRenewal === 1)
            Tu suscripción a <strong>{{ $subscription['plan_name'] ?? 'MyTaxEU' }}</strong> se renovará automáticamente mañana.
        @else
            Tu suscripción a <strong>{{ $subscription['plan_name'] ?? 'MyTaxEU' }}</strong> se renovará automáticamente en <strong>{{ $daysUntilRenewal }} días</strong>.
        @endif
    </p>

    @component('emails.components.card', ['type' => 'highlight', 'title' => 'Detalles de la Renovación'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 40%;">Plan actual:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $subscription['plan_name'] ?? 'Plan MyTaxEU' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Fecha de renovación:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ isset($subscription['next_billing_date']) ? \Carbon\Carbon::parse($subscription['next_billing_date'])->format('d/m/Y') : 'Por determinar' }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Importe:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ number_format($subscription['amount'] ?? 0, 2) }} {{ strtoupper($subscription['currency'] ?? 'EUR') }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Método de pago:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ $subscription['payment_method'] ?? 'Tarjeta terminada en ****' }}
                </td>
            </tr>
        </table>
    @endcomponent

    @component('emails.components.metrics', ['metrics' => [
        'Archivos Procesados' => number_format($usage['files_processed'] ?? 0),
        'Créditos Utilizados' => number_format($usage['credits_used'] ?? 0),
        'Tiempo Ahorrado' => ($usage['time_saved_hours'] ?? 0) . ' horas'
    ]])
    @endcomponent

    @if(($usage['files_processed'] ?? 0) > 0)
        @component('emails.components.card', ['type' => 'success', 'title' => '📊 Tu Productividad Este Mes'])
            <p style="margin: 0 0 12px 0; color: #065f46;">
                ¡Excelente trabajo! Este mes has procesado <strong>{{ number_format($usage['files_processed']) }} archivos</strong>
                y has ahorrado aproximadamente <strong>{{ $usage['time_saved_hours'] ?? 0 }} horas</strong> de trabajo manual.
            </p>

            @if(isset($usage['cost_savings']) && $usage['cost_savings'] > 0)
                <p style="margin: 0; color: #065f46;">
                    Esto representa un ahorro estimado de <strong>{{ number_format($usage['cost_savings']) }}€</strong>
                    en costes operativos.
                </p>
            @endif
        @endcomponent
    @else
        @component('emails.components.alert', ['type' => 'info', 'title' => '💡 ¿Sabías que puedes ahorrar más tiempo?'])
            Aún no has aprovechado al máximo tu suscripción este mes. Con MyTaxEU puedes:
            <ul style="margin: 8px 0 0 20px; padding: 0; color: #1e40af;">
                <li>Procesar archivos CSV de Amazon automáticamente</li>
                <li>Generar informes para modelos 349 y 369 en segundos</li>
                <li>Validar números de IVA instantáneamente</li>
                <li>Ahorrar hasta 8 horas por cliente al mes</li>
            </ul>
        @endcomponent
    @endif

    <div style="text-align: center; margin: 32px 0;">
        @component('emails.components.button', ['url' => config('app.url') . '/dashboard', 'type' => 'primary'])
            📊 Ver Dashboard
        @endcomponent

        @component('emails.components.button', ['url' => config('app.url') . '/subscription', 'type' => 'secondary'])
            ⚙️ Gestionar Suscripción
        @endcomponent>
    </div>

    @if($daysUntilRenewal <= 3)
        @component('emails.components.alert', ['type' => 'warning', 'title' => '⏰ Última Oportunidad'])
            Si deseas cambiar tu plan o cancelar tu suscripción, hazlo antes de la fecha de renovación.
            Los cambios realizados después de la renovación se aplicarán en el siguiente ciclo de facturación.
        @endcomponent>
    @endif

    <div style="margin: 32px 0 16px 0;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            ¿Necesitas Ayuda?
        </h3>
        <p class="email-text">
            Nuestro equipo de soporte está disponible para ayudarte con cualquier pregunta sobre tu suscripción
            o el uso de la plataforma.
        </p>

        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;">
            <tr>
                <td style="padding-right: 16px;">
                    <a href="mailto:{{ config('emails.content.support_email', 'soporte@mytaxeu.com') }}"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        📧 soporte@mytaxeu.com
                    </a>
                </td>
                <td>
                    <a href="{{ config('app.url') }}/help"
                       style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        📚 Centro de Ayuda
                    </a>
                </td>
            </tr>
        </table>
    </div>

    <p class="email-text-small">
        <strong>Nota:</strong> Tu suscripción se renovará automáticamente. Si no deseas renovar,
        puedes cancelar desde tu dashboard antes de la fecha de renovación.
        No se realizarán reembolsos por suscripciones ya facturadas.
    </p>
@endsection


