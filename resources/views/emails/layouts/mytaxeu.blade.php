<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('subject', 'MyTaxEU')</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }

        /* MyTaxEU Color Variables */
        :root {
            --primary-color: #1e40af;
            --primary-light: #3b82f6;
            --primary-lighter: #60a5fa;
            --accent-color: #fbbf24;
            --accent-light: #fcd34d;
            --success-color: #10b981;
            --error-color: #ef4444;
            --gray-dark: #111827;
            --gray-medium: #6b7280;
            --gray-light: #f3f4f6;
            --white: #ffffff;
        }

        /* Base styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #f3f4f6 !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
            line-height: 1.6 !important;
            color: #111827 !important;
        }

        /* Container styles */
        .email-container {
            max-width: 600px !important;
            margin: 0 auto !important;
            background-color: #ffffff !important;
            border-radius: 8px !important;
            overflow: visible !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07) !important;
        }

        /* Header styles */
        .email-header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%) !important;
            padding: 24px !important;
            text-align: center !important;
        }

        .email-logo {
            font-size: 32px !important;
            font-weight: 900 !important;
            color: #ffffff !important;
            text-decoration: none !important;
            margin: 0 !important;
        }

        .email-tagline {
            font-size: 14px !important;
            color: rgba(255, 255, 255, 0.9) !important;
            margin: 8px 0 0 0 !important;
            font-weight: 500 !important;
        }

        /* Content styles */
        .email-content {
            padding: 32px 24px !important;
        }

        .email-title {
            font-size: 24px !important;
            font-weight: 700 !important;
            color: #111827 !important;
            margin: 0 0 16px 0 !important;
            line-height: 1.3 !important;
        }

        .email-text {
            font-size: 16px !important;
            color: #374151 !important;
            margin: 0 0 16px 0 !important;
            line-height: 1.6 !important;
        }

        .email-text-small {
            font-size: 14px !important;
            color: #6b7280 !important;
            margin: 0 0 12px 0 !important;
        }

        /* Button styles */
        .email-button {
            display: inline-block !important;
            padding: 14px 28px !important;
            background-color: #1e40af !important;
            color: #ffffff !important;
            text-decoration: none !important;
            border-radius: 6px !important;
            font-weight: 600 !important;
            font-size: 16px !important;
            text-align: center !important;
            margin: 16px 0 !important;
            border: none !important;
            cursor: pointer !important;
        }

        .email-button:hover {
            background-color: #1e3a8a !important;
        }

        .email-button-secondary {
            background-color: #f3f4f6 !important;
            color: #1e40af !important;
            border: 2px solid #1e40af !important;
        }

        .email-button-secondary:hover {
            background-color: #e5e7eb !important;
        }

        .email-button-accent {
            background-color: #fbbf24 !important;
            color: #111827 !important;
        }

        .email-button-accent:hover {
            background-color: #f59e0b !important;
        }

        /* Card styles */
        .email-card {
            background-color: #f9fafb !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 8px !important;
            padding: 20px !important;
            margin: 16px 0 !important;
        }

        .email-card-highlight {
            background-color: #eff6ff !important;
            border-color: #3b82f6 !important;
        }

        .email-card-success {
            background-color: #ecfdf5 !important;
            border-color: #10b981 !important;
        }

        .email-card-warning {
            background-color: #fffbeb !important;
            border-color: #fbbf24 !important;
        }

        .email-card-error {
            background-color: #fef2f2 !important;
            border-color: #ef4444 !important;
        }

        /* Alert styles */
        .email-alert {
            padding: 16px !important;
            border-radius: 6px !important;
            margin: 16px 0 !important;
            border-left: 4px solid !important;
        }

        .email-alert-info {
            background-color: #eff6ff !important;
            border-left-color: #3b82f6 !important;
            color: #1e40af !important;
        }

        .email-alert-success {
            background-color: #ecfdf5 !important;
            border-left-color: #10b981 !important;
            color: #065f46 !important;
        }

        .email-alert-warning {
            background-color: #fffbeb !important;
            border-left-color: #fbbf24 !important;
            color: #92400e !important;
        }

        .email-alert-error {
            background-color: #fef2f2 !important;
            border-left-color: #ef4444 !important;
            color: #991b1b !important;
        }

        /* Metrics styles */
        .email-metrics {
            text-align: center !important;
            margin: 24px 0 !important;
        }

        .email-metric {
            display: inline-block !important;
            margin: 8px 16px !important;
            text-align: center !important;
        }

        .email-metric-value {
            font-size: 32px !important;
            font-weight: 900 !important;
            color: #1e40af !important;
            margin: 0 !important;
            line-height: 1 !important;
        }

        .email-metric-label {
            font-size: 14px !important;
            color: #6b7280 !important;
            margin: 4px 0 0 0 !important;
            font-weight: 500 !important;
        }

        /* Footer styles */
        .email-footer {
            background-color: #1f2937 !important;
            padding: 24px !important;
            color: #9ca3af !important;
            font-size: 14px !important;
            text-align: center !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
        }

        .email-footer a {
            color: #60a5fa !important;
            text-decoration: none !important;
        }

        .email-footer a:hover {
            text-decoration: underline !important;
        }

        .email-footer-divider {
            border: none !important;
            height: 1px !important;
            background-color: #374151 !important;
            margin: 16px 0 !important;
        }

        /* Responsive styles */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }

            .email-content {
                padding: 24px 16px !important;
            }

            .email-header {
                padding: 20px 16px !important;
            }

            .email-footer {
                padding: 20px 16px !important;
            }

            .email-title {
                font-size: 20px !important;
            }

            .email-button {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .email-metric {
                display: block !important;
                margin: 16px 0 !important;
            }
        }

        /* Selective word breaking for long strings only */
        .allow-break {
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
        }

        /* Fixed table layout for metrics */
        .email-metrics table {
            table-layout: fixed !important;
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-container {
                background-color: #1f2937 !important;
            }

            .email-content {
                background-color: #1f2937 !important;
            }

            .email-title {
                color: #ffffff !important;
            }

            .email-text {
                color: #e5e7eb !important;
            }
        }
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0; padding: 0; background-color: #f3f4f6;">
        <tr>
            <td style="padding: 20px 0;">
                <div class="email-container">
                    <!-- Header -->
                    @include('emails.components.header')

                    <!-- Main Content -->
                    <div class="email-content">
                        @yield('content')
                    </div>

                    <!-- Footer -->
                    @include('emails.components.footer')
                </div>
            </td>
        </tr>
    </table>
</body>
</html>


