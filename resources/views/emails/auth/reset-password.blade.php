@extends('emails.layouts.mytaxeu')

@section('subject', 'Restablecer Contraseña - MyTaxEU')

@section('content')
<div class="email-title" style="text-align: center; margin-bottom: 24px;">
    🔑 Restablecer tu Contraseña
</div>

<p class="email-text">
    ¡Hola!
</p>

<p class="email-text">
    Recibimos una solicitud para restablecer la contraseña de tu cuenta en MyTaxEU.
    Si fuiste tú quien realizó esta solicitud, haz clic en el botón de abajo para crear una nueva contraseña.
</p>

<div style="text-align: center; margin: 32px 0;">
    <a href="{{ $resetUrl }}" class="email-button email-button-accent" style="text-decoration: none;">
        Restablecer Contraseña
    </a>
</div>

<div class="email-alert email-alert-info">
    <p style="margin: 0; font-weight: 600;">⏰ Enlace de seguridad</p>
    <p style="margin: 8px 0 0 0; font-size: 14px;">
        Este enlace de restablecimiento es válido por <strong>60 minutos</strong> por razones de seguridad.
    </p>
</div>

<p class="email-text">
    Si no puedes hacer clic en el botón, copia y pega la siguiente URL en tu navegador:
</p>

<div class="email-card">
    <p style="margin: 0; font-family: monospace; font-size: 14px; word-break: break-all; color: #1e40af;">
        {{ $resetUrl }}
    </p>
</div>

<div class="email-alert email-alert-warning">
    <p style="margin: 0; font-weight: 600;">🛡️ Importante</p>
    <p style="margin: 8px 0 0 0; font-size: 14px;">
        Si <strong>no solicitaste</strong> este restablecimiento de contraseña, puedes ignorar este email de forma segura.
        Tu contraseña actual seguirá siendo válida.
    </p>
</div>

<p class="email-text">
    ¿Tienes problemas? Puedes contactar con nuestro equipo de soporte en cualquier momento.
</p>

<p class="email-text" style="margin-bottom: 0;">
    Saludos,<br>
    <strong>El equipo de MyTaxEU</strong>
</p>
@endsection
