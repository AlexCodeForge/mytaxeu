@extends('emails.layouts.mytaxeu')

@section('subject', '⚙️ Reporte Diario de Operaciones - ' . $reportDate)

@section('content')
    <h1 class="email-title">⚙️ Reporte Diario de Operaciones</h1>

    <p class="email-text">
        Resumen operacional del <strong>{{ \Carbon\Carbon::parse($reportDate)->format('d/m/Y') }}</strong>
    </p>

    @component('emails.components.metrics', ['metrics' => [
        'Trabajos Totales' => number_format($jobData['jobs']['total_jobs'] ?? 0),
        'Éxito' => number_format($jobData['jobs']['completed_jobs'] ?? 0),
        'Fallos' => number_format($jobData['jobs']['failed_jobs'] ?? 0),
        'Tasa de Éxito' => number_format($jobData['jobs']['success_rate'] ?? 0, 1) . '%'
    ]])
    @endcomponent

    @php
        $successRate = $jobData['jobs']['success_rate'] ?? 100;
        $isHealthy = $successRate >= 95;
    @endphp

    @component('emails.components.alert', ['type' => $isHealthy ? 'success' : 'warning', 'title' => '🎯 Estado del Sistema'])
        @if($isHealthy)
            <strong>Sistema funcionando correctamente</strong><br>
            La tasa de éxito está dentro de los parámetros normales (≥95%).
        @else
            <strong>Atención requerida</strong><br>
            La tasa de éxito ({{ number_format($successRate, 1) }}%) está por debajo del objetivo (95%).
        @endif
    @endcomponent

    @component('emails.components.card', ['type' => 'default', 'title' => '📊 Estadísticas de Procesamiento'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500; width: 45%;">Trabajos completados:</td>
                <td style="padding: 8px 0; font-weight: 600; color: #10b981;">{{ number_format($jobData['jobs']['completed_jobs'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Trabajos fallidos:</td>
                <td style="padding: 8px 0; font-weight: 600; color: #ef4444;">{{ number_format($jobData['jobs']['failed_jobs'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">En procesamiento:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($jobData['jobs']['processing_jobs'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">En cola:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($jobData['jobs']['queued_jobs'] ?? 0) }}</td>
            </tr>
        </table>
    @endcomponent

    @component('emails.components.card', ['type' => 'highlight', 'title' => '📈 Rendimiento'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Líneas procesadas:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($jobData['performance']['total_lines_processed'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Datos procesados:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($jobData['performance']['total_data_processed_mb'] ?? 0, 1) }} MB</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tiempo promedio:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($jobData['performance']['average_processing_time'] ?? 0, 1) }} segundos</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Créditos consumidos:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($jobData['performance']['total_credits_consumed'] ?? 0) }}</td>
            </tr>
        </table>
    @endcomponent

    @if(($jobData['jobs']['failed_jobs'] ?? 0) > 0)
        @component('emails.components.card', ['type' => 'error', 'title' => '❌ Trabajos Fallidos'])
            <p style="margin: 0 0 12px 0; color: #991b1b;">
                Se registraron <strong>{{ $jobData['jobs']['failed_jobs'] }}</strong> fallos hoy.
                Esto representa el <strong>{{ number_format($jobData['jobs']['failure_rate'] ?? 0, 1) }}%</strong> del total.
            </p>

            @if(isset($jobData['failure_reasons']) && count($jobData['failure_reasons']) > 0)
                <div style="margin-top: 12px;">
                    <strong style="color: #991b1b;">Principales causas:</strong>
                    <ul style="margin: 8px 0 0 20px; padding: 0; color: #7f1d1d;">
                        @foreach(array_slice($jobData['failure_reasons'], 0, 3) as $reason => $count)
                            <li style="margin-bottom: 4px;">{{ $reason }}: {{ $count }} casos</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endcomponent
    @endif

    @if(isset($jobData['hourly_breakdown']) && count($jobData['hourly_breakdown']) > 0)
        @component('emails.components.card', ['type' => 'default', 'title' => '⏰ Distribución Horaria'])
            <div style="margin: 0;">
                @php
                    $maxJobs = max(array_column($jobData['hourly_breakdown'], 'total'));
                @endphp
                @foreach($jobData['hourly_breakdown'] as $hour => $data)
                    @if(($data['total'] ?? 0) > 0)
                        @php
                            $percentage = $maxJobs > 0 ? ($data['total'] / $maxJobs) * 100 : 0;
                        @endphp
                        <div style="margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="font-weight: 500; color: #374151;">{{ $hour }}:00</span>
                                <span style="font-weight: 600;">
                                    {{ $data['total'] ?? 0 }} trabajos
                                    @if(($data['failed'] ?? 0) > 0)
                                        <span style="color: #ef4444;">({{ $data['failed'] }} fallos)</span>
                                    @endif
                                </span>
                            </div>
                            <div style="width: 100%; height: 6px; background-color: #e5e7eb; border-radius: 3px;">
                                <div style="width: {{ $percentage }}%; height: 100%; background-color: #3b82f6; border-radius: 3px;"></div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endcomponent
    @endif

    @if(isset($jobData['top_users']) && count($jobData['top_users']) > 0)
        @component('emails.components.card', ['type' => 'default', 'title' => '👥 Usuarios Más Activos'])
            <div style="margin: 0;">
                @foreach(array_slice($jobData['top_users'], 0, 5) as $index => $userData)
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px; margin-bottom: 6px; background-color: {{ $index === 0 ? '#eff6ff' : '#f8fafc' }}; border-radius: 4px;">
                        <div>
                            <span style="font-weight: 600; color: #1e40af;">{{ $userData['name'] ?? 'Usuario ' . ($index + 1) }}</span>
                            <br>
                            <span style="color: #6b7280; font-size: 14px;">{{ $userData['email'] ?? '' }}</span>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-weight: 600;">{{ $userData['jobs_count'] ?? 0 }} trabajos</span>
                            <br>
                            <span style="color: #6b7280; font-size: 14px;">{{ number_format($userData['lines_processed'] ?? 0) }} líneas</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endcomponent
    @endif

    @component('emails.components.card', ['type' => 'default', 'title' => '🔧 Estado de la Infraestructura'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Estado de la cola:</td>
                <td style="padding: 8px 0; font-weight: 600; color: {{ ($jobData['system_health']['queue_health'] ?? 'healthy') === 'healthy' ? '#10b981' : '#ef4444' }};">
                    {{ ($jobData['system_health']['queue_health'] ?? 'healthy') === 'healthy' ? 'Saludable ✅' : 'Problemas ⚠️' }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tiempo de actividad:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($jobData['system_health']['uptime_percentage'] ?? 99.9, 1) }}%</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tasa de error:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($jobData['system_health']['error_rate'] ?? 0, 2) }}%</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Trabajos en cola:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($jobData['jobs']['queued_jobs'] ?? 0) }}</td>
            </tr>
        </table>
    @endcomponent

    <div style="text-align: center; margin: 32px 0;">
        @component('emails.components.button', ['url' => config('app.url') . '/admin/jobs', 'type' => 'primary'])
            🔍 Ver Detalles de Trabajos
        @endcomponent

        @component('emails.components.button', ['url' => config('app.url') . '/admin/monitoring', 'type' => 'secondary'])
            📊 Panel de Monitoreo
        @endcomponent
    </div>

    @php
        $alerts = [];

        if (($jobData['jobs']['success_rate'] ?? 100) < 95) {
            $alerts[] = [
                'type' => 'warning',
                'title' => '⚠️ Tasa de éxito baja',
                'message' => 'La tasa de éxito está por debajo del 95%. Revisar logs de errores.',
            ];
        }

        if (($jobData['jobs']['queued_jobs'] ?? 0) > 50) {
            $alerts[] = [
                'type' => 'warning',
                'title' => '📈 Cola de trabajos alta',
                'message' => 'Hay más de 50 trabajos en cola. Considerar escalar recursos.',
            ];
        }

        if (($jobData['performance']['average_processing_time'] ?? 0) > 300) {
            $alerts[] = [
                'type' => 'warning',
                'title' => '⏱️ Tiempo de procesamiento alto',
                'message' => 'El tiempo promedio excede 5 minutos. Optimizar rendimiento.',
            ];
        }

        if (empty($alerts)) {
            $alerts[] = [
                'type' => 'success',
                'title' => '✅ Todo funcionando correctamente',
                'message' => 'Todos los indicadores están dentro de los parámetros normales.',
            ];
        }
    @endphp

    @foreach($alerts as $alert)
        @component('emails.components.alert', ['type' => $alert['type'], 'title' => $alert['title']])
            {{ $alert['message'] }}
        @endcomponent
    @endforeach

    <div style="margin: 32px 0 16px 0;">
        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #111827;">
            Recomendaciones para Mañana
        </h3>

        @php
            $recommendations = [];

            if (($jobData['jobs']['failed_jobs'] ?? 0) > 5) {
                $recommendations[] = 'Revisar y solucionar las causas principales de fallos';
            }

            if (($jobData['jobs']['queued_jobs'] ?? 0) > 20) {
                $recommendations[] = 'Monitorear la cola de trabajos durante horas pico';
            }

            if (($jobData['performance']['average_processing_time'] ?? 0) > 180) {
                $recommendations[] = 'Optimizar el rendimiento del sistema de procesamiento';
            }

            if (empty($recommendations)) {
                $recommendations[] = 'Continuar monitoreando el sistema';
                $recommendations[] = 'Mantener los estándares de calidad actuales';
            }
        @endphp

        <ul style="margin: 0; padding-left: 20px; color: #374151;">
            @foreach($recommendations as $recommendation)
                <li style="margin-bottom: 6px;">{{ $recommendation }}</li>
            @endforeach
        </ul>
    </div>

    <p class="email-text-small">
        <strong>Nota:</strong> Este reporte diario se genera automáticamente cada día a las 8:00 AM
        con datos del día anterior. Proporciona una visión operacional para mantener la calidad del servicio.
    </p>

    <p class="email-text-small">
        Reporte generado: {{ now()->format('d/m/Y H:i') }} |
        Datos del: {{ \Carbon\Carbon::parse($reportDate)->format('d/m/Y') }} |
        Próximo reporte: {{ now()->addDay()->format('d/m/Y 08:00') }}
    </p>
@endsection


