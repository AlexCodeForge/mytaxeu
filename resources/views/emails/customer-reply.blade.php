<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Respuesta de MyTaxEU</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .content {
            background: white;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .message-content {
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 2px solid #3b82f6;
            font-size: 15px;
            line-height: 1.8;
            color: #1f2937;
        }
        .footer {
            background: #f8fafc;
            padding: 20px;
            text-align: center;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 8px 8px;
            font-size: 14px;
            color: #6b7280;
        }
        .signature {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
        .contact-info {
            margin-top: 15px;
        }
        .contact-info a {
            color: #3b82f6;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">MyTaxEU</div>
        <div>Respuesta a tu consulta</div>
    </div>

    <div class="content">
        <p>Hola {{ $customerName }},</p>

        <p>Hemos recibido tu mensaje y te respondemos a continuación:</p>

        <div class="message-content" style="display: block !important;">
            <div style="display: block !important; visibility: visible !important;">
                {!! nl2br(e($messageBody)) !!}
            </div>
        </div>

        <p>Si tienes alguna pregunta adicional o necesitas más ayuda, no dudes en responder a este email.</p>

        <div class="signature">
            <strong>{{ $adminName }}</strong><br>
            Equipo de Soporte MyTaxEU

            <div class="contact-info">
                <strong>Contacto:</strong><br>
                📧 Email: <a href="mailto:soporte@mytaxeu.com">soporte@mytaxeu.com</a><br>
                🌐 Web: <a href="https://mytaxeu.alexcodeforge.com">mytaxeu.alexcodeforge.com</a>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Este email fue enviado desde el sistema de soporte de MyTaxEU.</p>
        <p>© {{ date('Y') }} MyTaxEU - Automatiza la Gestión Fiscal de Amazon</p>
    </div>
</body>
</html>
