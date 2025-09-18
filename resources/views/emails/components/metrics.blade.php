{{-- Email Metrics Component --}}
@props([
    'metrics' => [], // Array of ['label' => 'value'] pairs
    'columns' => 'auto', // auto, 1, 2, 3, 4
    'centered' => true
])

@php
    $containerStyle = $centered ? 'text-align: center;' : '';

    // Calculate column width based on metrics count and columns setting
    $metricsCount = count($metrics);
    if ($columns === 'auto') {
        $columnWidth = $metricsCount > 0 ? (100 / $metricsCount) : 100;
    } else {
        $columnWidth = 100 / (int)$columns;
    }
@endphp

<div class="email-metrics" style="{{ $containerStyle }}">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 24px 0; table-layout: fixed;">
        <tr>
            @foreach($metrics as $label => $value)
                <td style="width: {{ $columnWidth }}%; text-align: center; vertical-align: top; padding: 8px;">
                    <div class="email-metric">
                        <div class="email-metric-value">{{ $value }}</div>
                        <div class="email-metric-label">{{ $label }}</div>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
</div>

{{-- Alternative usage for custom content --}}
@if(trim($slot))
    <div class="email-metrics" style="{{ $containerStyle }}">
        {{ $slot }}
    </div>
@endif


