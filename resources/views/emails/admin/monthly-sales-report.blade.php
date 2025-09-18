@extends('emails.layouts.mytaxeu')

@section('subject', '📈 Reporte Ejecutivo Mensual - ' . $monthPeriod)

@section('content')
    <h1 class="email-title">📈 Reporte Ejecutivo Mensual</h1>

    <p class="email-text">
        Análisis completo del rendimiento comercial para <strong>{{ $monthPeriod }}</strong>
    </p>

    @component('emails.components.metrics', ['metrics' => [
        'Ingresos del Mes' => '€' . number_format($reportData['sales']['total_revenue'] ?? 0, 0),
        'Ventas Totales' => number_format($reportData['sales']['total_sales'] ?? 0),
        'Nuevos Clientes' => number_format($reportData['customers']['new_users'] ?? 0),
        'Crecimiento' => number_format($reportData['growth']['revenue_growth_rate'] ?? 0, 1) . '%'
    ]])
    @endcomponent

    @if(isset($reportData['growth']['revenue_growth_rate']))
        @php
            $growthRate = $reportData['growth']['revenue_growth_rate'];
            $isPositive = $growthRate >= 0;
        @endphp
        @component('emails.components.card', ['type' => $isPositive ? 'success' : 'warning', 'title' => '📊 Rendimiento vs Mes Anterior'])
            <div style="text-align: center; margin-bottom: 16px;">
                <div style="font-size: 32px; font-weight: 900; color: {{ $isPositive ? '#10b981' : '#f59e0b' }}; margin-bottom: 8px;">
                    {{ $isPositive ? '↗️' : '↘️' }} {{ number_format(abs($growthRate), 1) }}%
                </div>
                <div style="font-size: 16px; font-weight: 600; color: #6b7280;">
                    {{ $isPositive ? 'Crecimiento' : 'Descenso' }} en ingresos
                </div>
            </div>

            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Mes anterior:</td>
                    <td style="padding: 8px 0; font-weight: 600; text-align: right;">€{{ number_format($reportData['growth']['previous_period_revenue'] ?? 0, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Este mes:</td>
                    <td style="padding: 8px 0; font-weight: 600; text-align: right; color: #10b981;">€{{ number_format($reportData['growth']['current_period_revenue'] ?? 0, 2) }}</td>
                </tr>
                <tr style="border-top: 2px solid #e5e7eb;">
                    <td style="padding: 8px 0; color: #111827; font-weight: 600;">Diferencia:</td>
                    <td style="padding: 8px 0; font-weight: 700; text-align: right; color: {{ $isPositive ? '#10b981' : '#ef4444' }};">
                        {{ $isPositive ? '+' : '' }}€{{ number_format(($reportData['growth']['current_period_revenue'] ?? 0) - ($reportData['growth']['previous_period_revenue'] ?? 0), 2) }}
                    </td>
                </tr>
            </table>
        @endcomponent
    @endif

    @component('emails.components.card', ['type' => 'highlight', 'title' => '💰 Resumen Financiero'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 12px 0; color: #6b7280; font-weight: 500; width: 50%;">Ingresos totales:</td>
                <td style="padding: 12px 0; font-weight: 700; font-size: 18px; color: #10b981;">
                    €{{ number_format($reportData['sales']['total_revenue'] ?? 0, 2) }}
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0; color: #6b7280; font-weight: 500;">Número de transacciones:</td>
                <td style="padding: 12px 0; font-weight: 600;">{{ number_format($reportData['sales']['total_sales'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0; color: #6b7280; font-weight: 500;">Ticket promedio:</td>
                <td style="padding: 12px 0; font-weight: 600;">€{{ number_format($reportData['sales']['average_transaction_value'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 0; color: #6b7280; font-weight: 500;">Ingresos recurrentes:</td>
                <td style="padding: 12px 0; font-weight: 600;">
                    €{{ number_format(($reportData['sales']['total_revenue'] ?? 0) * 0.85, 2) }}
                    <span style="color: #6b7280; font-size: 14px;">(85% estimado)</span>
                </td>
            </tr>
        </table>
    @endcomponent

    @component('emails.components.card', ['type' => 'success', 'title' => '👥 Análisis de Clientes'])
        <div style="margin-bottom: 16px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                <span style="font-weight: 500; color: #374151;">Nuevos clientes:</span>
                <span style="font-weight: 600; color: #10b981;">{{ number_format($reportData['customers']['new_users'] ?? 0) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                <span style="font-weight: 500; color: #374151;">Clientes activos:</span>
                <span style="font-weight: 600;">{{ number_format($reportData['customers']['active_users'] ?? 0) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                <span style="font-weight: 500; color: #374151;">Tasa de conversión:</span>
                <span style="font-weight: 600;">{{ number_format($reportData['customers']['conversion_rate'] ?? 0, 1) }}%</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="font-weight: 500; color: #374151;">Tasa de activación:</span>
                <span style="font-weight: 600;">{{ number_format($reportData['customers']['activation_rate'] ?? 0, 1) }}%</span>
            </div>
        </div>

        @if(($reportData['customers']['conversion_rate'] ?? 0) > 15)
            <div style="padding: 12px; background-color: #ecfdf5; border-radius: 6px; border-left: 4px solid #10b981;">
                <span style="color: #065f46; font-weight: 600;">🎯 Excelente tasa de conversión</span>
            </div>
        @elseif(($reportData['customers']['conversion_rate'] ?? 0) < 5)
            <div style="padding: 12px; background-color: #fffbeb; border-radius: 6px; border-left: 4px solid #f59e0b;">
                <span style="color: #92400e; font-weight: 600;">⚠️ Oportunidad de mejora en conversión</span>
            </div>
        @endif
    @endcomponent

    @component('emails.components.card', ['type' => 'default', 'title' => '📋 Distribución de Planes'])
        @if(isset($reportData['plans']) && count($reportData['plans']) > 0)
            @php
                $totalPlanSales = array_sum($reportData['plans']);
            @endphp
            <div style="margin: 0;">
                @foreach($reportData['plans'] as $plan => $count)
                    @if($count > 0)
                        @php
                            $percentage = $totalPlanSales > 0 ? ($count / $totalPlanSales) * 100 : 0;
                        @endphp
                        <div style="margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <span style="font-weight: 600; color: #1e40af;">{{ $plan }}</span>
                                <span style="font-weight: 600;">{{ number_format($count) }} ({{ number_format($percentage, 1) }}%)</span>
                            </div>
                            <div style="width: 100%; height: 8px; background-color: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                <div style="width: {{ $percentage }}%; height: 100%; background-color: #3b82f6; border-radius: 4px;"></div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            <p style="margin: 0; color: #6b7280; font-style: italic;">No hay datos de distribución de planes disponibles.</p>
        @endif
    @endcomponent

    @component('emails.components.card', ['type' => 'default', 'title' => '⚙️ Operaciones y Rendimiento'])
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Archivos procesados:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($reportData['jobs']['completed_jobs'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tasa de éxito:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($reportData['jobs']['success_rate'] ?? 0, 1) }}%</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Tiempo promedio de procesamiento:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($reportData['performance']['average_processing_time'] ?? 0, 1) }}s</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Líneas procesadas:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($reportData['performance']['total_lines_processed'] ?? 0) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Datos procesados:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($reportData['performance']['total_data_processed_mb'] ?? 0, 1) }} MB</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280; font-weight: 500;">Créditos consumidos:</td>
                <td style="padding: 8px 0; font-weight: 600;">{{ number_format($reportData['performance']['total_credits_consumed'] ?? 0) }}</td>
            </tr>
        </table>
    @endcomponent

    @php
        $successRate = $reportData['jobs']['success_rate'] ?? 100;
        $growthRate = $reportData['growth']['revenue_growth_rate'] ?? 0;
        $conversionRate = $reportData['customers']['conversion_rate'] ?? 0;
    @endphp

    @component('emails.components.card', ['type' => 'highlight', 'title' => '🎯 Objetivos y KPIs'])
        <div style="margin: 0;">
            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="font-weight: 500;">Tasa de éxito (objetivo: >95%)</span>
                    <span style="font-weight: 600; color: {{ $successRate >= 95 ? '#10b981' : '#f59e0b' }};">
                        {{ number_format($successRate, 1) }}% {{ $successRate >= 95 ? '✅' : '⚠️' }}
                    </span>
                </div>
                <div style="width: 100%; height: 6px; background-color: #e5e7eb; border-radius: 3px;">
                    <div style="width: {{ min($successRate, 100) }}%; height: 100%; background-color: {{ $successRate >= 95 ? '#10b981' : '#f59e0b' }}; border-radius: 3px;"></div>
                </div>
            </div>

            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="font-weight: 500;">Crecimiento mensual (objetivo: >10%)</span>
                    <span style="font-weight: 600; color: {{ $growthRate >= 10 ? '#10b981' : '#f59e0b' }};">
                        {{ number_format($growthRate, 1) }}% {{ $growthRate >= 10 ? '✅' : '⚠️' }}
                    </span>
                </div>
                <div style="width: 100%; height: 6px; background-color: #e5e7eb; border-radius: 3px;">
                    <div style="width: {{ min(max($growthRate / 20 * 100, 0), 100) }}%; height: 100%; background-color: {{ $growthRate >= 10 ? '#10b981' : '#f59e0b' }}; border-radius: 3px;"></div>
                </div>
            </div>

            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span style="font-weight: 500;">Conversión (objetivo: >12%)</span>
                    <span style="font-weight: 600; color: {{ $conversionRate >= 12 ? '#10b981' : '#f59e0b' }};">
                        {{ number_format($conversionRate, 1) }}% {{ $conversionRate >= 12 ? '✅' : '⚠️' }}
                    </span>
                </div>
                <div style="width: 100%; height: 6px; background-color: #e5e7eb; border-radius: 3px;">
                    <div style="width: {{ min($conversionRate / 20 * 100, 100) }}%; height: 100%; background-color: {{ $conversionRate >= 12 ? '#10b981' : '#f59e0b' }}; border-radius: 3px;"></div>
                </div>
            </div>
        </div>
    @endcomponent

    <div style="text-align: center; margin: 32px 0;">
        @component('emails.components.button', ['url' => config('app.url') . '/admin/reports/monthly', 'type' => 'accent'])
            📊 Ver Análisis Detallado
        @endcomponent

        @component('emails.components.button', ['url' => config('app.url') . '/admin/dashboard', 'type' => 'primary'])
            🎛️ Dashboard Ejecutivo
        @endcomponent
    </div>

    @component('emails.components.card', ['type' => 'default', 'title' => '📋 Plan de Acción para el Próximo Mes'])
        @php
            $actions = [];

            if ($growthRate < 10) {
                $actions[] = '📈 Implementar estrategias de crecimiento: optimizar pricing, expandir marketing';
            }

            if ($conversionRate < 12) {
                $actions[] = '🎯 Mejorar embudo de conversión: onboarding, trial experience, seguimiento';
            }

            if ($successRate < 95) {
                $actions[] = '🔧 Optimizar sistema de procesamiento: reducir fallos, mejorar estabilidad';
            }

            if (($reportData['sales']['total_sales'] ?? 0) > 50) {
                $actions[] = '🚀 Escalar operaciones: automatización adicional, recursos del equipo';
            }

            if (empty($actions)) {
                $actions[] = '✅ Continuar con la estrategia actual - rendimiento óptimo';
                $actions[] = '🔍 Explorar nuevas oportunidades de crecimiento';
                $actions[] = '💡 Innovar en características del producto';
            }
        @endphp

        <ul style="margin: 0; padding-left: 20px; color: #374151;">
            @foreach($actions as $action)
                <li style="margin-bottom: 8px; font-weight: 500;">{{ $action }}</li>
            @endforeach
        </ul>
    @endcomponent

    <p class="email-text-small">
        <strong>Nota:</strong> Este reporte ejecutivo mensual se genera automáticamente el primer día de cada mes.
        Incluye análisis comparativo, proyecciones y recomendaciones estratégicas basadas en datos de rendimiento.
    </p>

    <p class="email-text-small">
        Reporte generado: {{ now()->format('d/m/Y H:i') }} |
        Período analizado: {{ $reportData['sales']['period']['start_date'] ?? 'N/A' }} - {{ $reportData['sales']['period']['end_date'] ?? 'N/A' }} |
        Próximo reporte: {{ now()->addMonth()->startOfMonth()->format('d/m/Y') }}
    </p>
@endsection


