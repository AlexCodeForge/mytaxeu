@extends('emails.layouts.mytaxeu')

@section('subject', '⚠️ Alerta de Trabajo Fallido - MyTaxEU')

@section('content')
    @php
        $severityConfig = [
            'critical' => ['color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => '🚨', 'label' => 'CRÍTICO'],
            'high' => ['color' => '#ea580c', 'bg' => '#fff7ed', 'icon' => '🔴', 'label' => 'ALTA'],
            'medium' => ['color' => '#d97706', 'bg' => '#fffbeb', 'icon' => '🟡', 'label' => 'MEDIA'],
            'low' => ['color' => '#16a34a', 'bg' => '#f0fdf4', 'icon' => '🟢', 'label' => 'BAJA'],
        ];

        $severityStyle = $severityConfig[$severity] ?? $severityConfig['low'];
    @endphp

    <div style="background-color: {{ $severityStyle['bg'] }}; border-left: 4px solid {{ $severityStyle['color'] }}; padding: 16px; border-radius: 6px; margin-bottom: 24px;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: {{ $severityStyle['color'] }};">
            {{ $severityStyle['icon'] }} ALERTA DE TRABAJO FALLIDO
        </h1>
        <p style="margin: 8px 0 0 0; font-size: 16px; color: {{ $severityStyle['color'] }}; font-weight: 600;">
            Severidad: {{ $severityStyle['label'] }} | {{ now()->format('d/m/Y H:i:s') }}
        </p>
    </div>

    @component('emails.components.card', ['type' => 'error', 'title' => '🎯 Resumen del Incidente'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #7f1d1d; font-weight: 500; width: 30%;">Trabajo:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $jobDetails['type'] ?? 'ProcessUploadJob' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #7f1d1d; font-weight: 500;">ID del trabajo:</td>
                <td style="padding: 8px 0; font-weight: 600; font-family: monospace;">{{ $jobDetails['job_id'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #7f1d1d; font-weight: 500;">Archivo:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $jobDetails['filename'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #7f1d1d; font-weight: 500;">Usuario afectado:</td>
                <td style="padding: 8px 0; font-weight: 600;">
                    {{ $jobDetails['user_email'] ?? 'N/A' }}
                    @if(isset($jobDetails['user_id']))
                        <span style="color: #6b7280; font-size: 14px;">(ID: {{ $jobDetails['user_id'] }})</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #7f1d1d; font-weight: 500;">Hora del fallo:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $jobDetails['failed_at'] ?? now()->format('d/m/Y H:i:s') }}</td>
            </tr>
        </table>
    @endcomponent

    @component('emails.components.card', ['type' => 'warning', 'title' => '⚠️ Detalles del Error'])
        <div style="margin-bottom: 16px;">
            <strong style="color: #92400e;">Tipo de error:</strong>
            <span style="display: inline-block; background-color: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-weight: 600; margin-left: 8px;">
                {{ $errorDetails['type'] ?? 'Error desconocido' }}
            </span>
        </div>

        <div style="margin-bottom: 16px;">
            <strong style="color: #92400e;">Mensaje de error:</strong>
            <div style="background-color: #fffbeb; border: 1px solid #f3e8ff; border-radius: 4px; padding: 12px; margin-top: 8px; font-family: monospace; font-size: 14px; color: #78350f; line-height: 1.4;">
                {{ $errorDetails['message'] ?? 'No se proporcionó mensaje de error' }}
            </div>
        </div>

        @if(isset($errorDetails['stack_trace']) && $errorDetails['stack_trace'])
            <div style="margin-bottom: 16px;">
                <strong style="color: #92400e;">Stack trace (primeras líneas):</strong>
                <div style="background-color: #fffbeb; border: 1px solid #f3e8ff; border-radius: 4px; padding: 12px; margin-top: 8px; font-family: monospace; font-size: 12px; color: #78350f; max-height: 150px; overflow: auto;" class="allow-break">
                    {{ Str::limit($errorDetails['stack_trace'], 500, '') }}
                </div>
            </div>
        @endif

        @if(isset($jobDetails['retry_count']) && $jobDetails['retry_count'] > 0)
            <div>
                <strong style="color: #92400e;">Intentos de reintento:</strong>
                <span style="color: #ef4444; font-weight: 600;">{{ $jobDetails['retry_count'] }}/{{ $jobDetails['max_retries'] ?? 3 }}</span>
            </div>
        @endif
    @endcomponent

    @component('emails.components.card', ['type' => 'default', 'title' => '📊 Contexto del Sistema'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Fallos recientes (última hora):</td>
                <td style="padding: 8px 0; font-weight: 600; color: {{ ($systemContext['recent_failures'] ?? 0) > 5 ? '#ef4444' : '#6b7280' }};">
                    {{ $systemContext['recent_failures'] ?? 0 }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Trabajos en cola:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $systemContext['queue_size'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Estado de la cola:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $systemContext['queue_health'] ?? 'Desconocido' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Uso de memoria:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $systemContext['memory_usage'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Carga del sistema:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ $systemContext['system_load'] ?? 'N/A' }}</td>
            </tr>
        </table>
    @endcomponent

    @if(isset($jobDetails['input_data']) && $jobDetails['input_data'])
        @component('emails.components.card', ['type' => 'default', 'title' => '📄 Información del Archivo'])
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Nombre del archivo:</td>
                    <td style="padding: 8px 0; font-weight: 600;">{{ $jobDetails['filename'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tamaño:</td>
                    <td style="padding: 8px 0; font-weight: 600;">{{ $jobDetails['file_size'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Líneas CSV:</td>
                    <td style="padding: 8px 0; font-weight: 600;">{{ $jobDetails['csv_lines'] ?? 'N/A' }}</td>
                </tr>
                @if(isset($jobDetails['processing_duration']))
                    <tr>
                        <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tiempo de procesamiento:</td>
                        <td style="padding: 8px 0; font-weight: 600;">{{ $jobDetails['processing_duration'] }} segundos</td>
                    </tr>
                @endif
            </table>
        @endcomponent
    @endif

    @php
        $actionPriority = match($severity) {
            'critical' => 'inmediata',
            'high' => 'urgente',
            'medium' => 'pronta',
            default => 'rutinaria'
        };
    @endphp

    @component('emails.components.alert', ['type' => match($severity) { 'critical', 'high' => 'error', 'medium' => 'warning', default => 'info' }, 'title' => '🚀 Acción Requerida'])
        <strong>Prioridad: {{ ucfirst($actionPriority) }}</strong><br>

        @if($severity === 'critical')
            🚨 <strong>ACCIÓN INMEDIATA REQUERIDA</strong> - El sistema puede estar en riesgo
        @elseif($severity === 'high')
            🔴 <strong>Acción urgente necesaria</strong> - Múltiples usuarios pueden estar afectados
        @elseif($severity === 'medium')
            🟡 <strong>Revisión necesaria</strong> - Usuario específico afectado
        @else
            🟢 <strong>Monitoreo continuo</strong> - Incidente aislado
        @endif
    @endcomponent

    <div style="text-align: center; margin: 32px 0;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
            <tr>
                <td style="padding: 0 8px;">
                    @component('emails.components.button', ['url' => config('app.url') . '/admin/jobs/' . ($jobDetails['job_id'] ?? ''), 'type' => 'primary'])
                        🔍 Ver Detalles del Trabajo
                    @endcomponent
                </td>
                <td style="padding: 0 8px;">
                    @component('emails.components.button', ['url' => config('app.url') . '/admin/users/' . ($jobDetails['user_id'] ?? ''), 'type' => 'secondary'])
                        👤 Ver Usuario
                    @endcomponent
                </td>
                <td style="padding: 0 8px;">
                    @component('emails.components.button', ['url' => config('app.url') . '/admin/logs', 'type' => 'accent'])
                        📋 Ver Logs del Sistema
                    @endcomponent
                </td>
            </tr>
        </table>
    </div>

    @component('emails.components.card', ['type' => 'default', 'title' => '🛠️ Pasos de Diagnóstico Recomendados'])
        <ol style="margin: 0; padding-left: 20px; color: #374151;">
            @if($severity === 'critical' || $severity === 'high')
                <li style="margin-bottom: 8px; font-weight: 600;">Verificar inmediatamente el estado del sistema y la cola de trabajos</li>
                <li style="margin-bottom: 8px; font-weight: 600;">Revisar logs de aplicación y sistema para patrones de error</li>
                <li style="margin-bottom: 8px;">Contactar al usuario afectado si es necesario</li>
            @else
                <li style="margin-bottom: 8px;">Revisar los logs del trabajo específico</li>
                <li style="margin-bottom: 8px;">Validar el formato y contenido del archivo subido</li>
            @endif
            <li style="margin-bottom: 8px;">Determinar si es un problema recurrente o aislado</li>
            <li style="margin-bottom: 8px;">Documentar la causa raíz una vez identificada</li>
            <li style="margin-bottom: 8px;">Implementar medidas preventivas si es aplicable</li>
        </ol>
    @endcomponent

    @if(isset($errorDetails['similar_errors']) && count($errorDetails['similar_errors']) > 0)
        @component('emails.components.card', ['type' => 'warning', 'title' => '🔄 Errores Similares Recientes'])
            <p style="margin: 0 0 12px 0; color: #92400e;">
                Se han detectado <strong>{{ count($errorDetails['similar_errors']) }}</strong> errores similares en las últimas 24 horas:
            </p>
            <ul style="margin: 0; padding-left: 20px; color: #78350f;">
                @foreach(array_slice($errorDetails['similar_errors'], 0, 3) as $error)
                    <li style="margin-bottom: 4px;">
                        {{ $error['time'] ?? 'N/A' }} - {{ $error['user'] ?? 'Usuario desconocido' }}
                    </li>
                @endforeach
                @if(count($errorDetails['similar_errors']) > 3)
                    <li style="margin-bottom: 4px; font-style: italic;">
                        ... y {{ count($errorDetails['similar_errors']) - 3 }} más
                    </li>
                @endif
            </ul>
        @endcomponent
    @endif

    <p class="email-text-small">
        <strong>Nota:</strong> Esta alerta se genera automáticamente cuando ocurre un fallo en el sistema.
        La severidad se calcula basándose en el tipo de error, frecuencia y contexto del sistema.
    </p>

    <p class="email-text-small">
        ID de la alerta: {{ uniqid('alert_') }} |
        Generada: {{ now()->format('d/m/Y H:i:s') }} |
        Servidor: {{ config('app.env') }}
    </p>
@endsection


