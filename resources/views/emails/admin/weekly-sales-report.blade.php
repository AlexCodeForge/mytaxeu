@extends('emails.layouts.mytaxeu')

@section('subject', '📊 Reporte Semanal de Ventas - ' . $weekPeriod)

@section('content')
    <h1 class="email-title">📊 Reporte Semanal de Ventas</h1>

    <p class="email-text">
        Resumen de actividad comercial para la semana del <strong>{{ $weekPeriod }}</strong>
    </p>

    @component('emails.components.card', ['type' => 'success', 'title' => '💰 Resumen de Ventas y Actividad'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 6px 0; color: #6b7280; font-weight: 500; width: 50%;">Ventas totales:</td>
                <td style="padding: 6px 0; font-weight: 600; text-align: right;">{{ number_format($reportData['sales']['total_sales'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280; font-weight: 500;">Ingresos totales:</td>
                <td style="padding: 6px 0; font-weight: 600; color: #10b981; text-align: right;">
                    €{{ number_format($reportData['sales']['total_revenue'] ?? 0, 2) }}
                </td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280; font-weight: 500;">Nuevos clientes:</td>
                <td style="padding: 6px 0; font-weight: 600; text-align: right;">{{ number_format($reportData['customers']['new_users'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280; font-weight: 500;">Archivos procesados:</td>
                <td style="padding: 6px 0; font-weight: 600; text-align: right;">{{ number_format($reportData['jobs']['completed_jobs'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280; font-weight: 500;">Tasa de éxito:</td>
                <td style="padding: 6px 0; font-weight: 600; text-align: right;">{{ number_format($reportData['jobs']['success_rate'] ?? 0, 1) }}%</td>
            </tr>
        </table>
    @endcomponent>

    @if(isset($reportData['growth']['revenue_growth_rate']))
        @php
            $growthRate = $reportData['growth']['revenue_growth_rate'];
            $isPositive = $growthRate >= 0;
        @endphp
        @component('emails.components.alert', ['type' => $isPositive ? 'success' : 'warning', 'title' => '📈 Crecimiento'])
            <strong>{{ $isPositive ? '↗️' : '↘️' }} {{ number_format(abs($growthRate), 1) }}%</strong> {{ $isPositive ? 'crecimiento' : 'descenso' }} vs semana anterior
        @endcomponent>
    @endif

    @if(($reportData['jobs']['failed_jobs'] ?? 0) > 0)
        @component('emails.components.alert', ['type' => 'warning', 'title' => '⚠️ Atención'])
            {{ $reportData['jobs']['failed_jobs'] }} trabajos fallidos esta semana.
            <a href="{{ config('app.url') }}/admin/jobs?status=failed" style="color: #1e40af;">Ver detalles →</a>
        @endcomponent>
    @endif

    <div style="text-align: center; margin: 24px 0;">
        @component('emails.components.button', ['url' => config('app.url') . '/admin/reports', 'type' => 'primary'])
            📊 Ver Dashboard Completo
        @endcomponent>
    </div>

    <p class="email-text-small" style="text-align: center; margin: 16px 0 0 0;">
        Reporte generado: {{ now()->format('d/m/Y H:i') }} | Período: {{ $weekPeriod }}
    </p>
@endsection


