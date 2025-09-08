{{-- Email Footer Component --}}
<div class="email-footer">
    <p style="margin: 0 0 16px 0; font-weight: 600; color: #ffffff;">MyTaxEU</p>
    <p style="margin: 0 0 16px 0;">
        Automatizamos la gestión fiscal de Amazon para que puedas enfocarte en hacer crecer tu negocio.
    </p>

    <hr class="email-footer-divider">

    <p style="margin: 0 0 12px 0;">
        <a href="{{ config('app.url') }}" style="margin: 0 8px;">Inicio</a>
        <a href="{{ config('app.url') }}/dashboard" style="margin: 0 8px;">Dashboard</a>
        <a href="{{ config('app.url') }}/support" style="margin: 0 8px;">Soporte</a>
    </p>

    <p style="margin: 0 0 16px 0; font-size: 12px;">
        Si no deseas recibir estos emails, puedes
        <a href="{{ config('app.url') }}/unsubscribe?token={{ $unsubscribeToken ?? '' }}">darte de baja aquí</a>.
    </p>

    <p style="margin: 0; font-size: 12px; color: #6b7280;">
        © {{ date('Y') }} MyTaxEU. Todos los derechos reservados.<br>
        Este email fue enviado a {{ $email ?? 'tu email' }}.
    </p>
</div>


