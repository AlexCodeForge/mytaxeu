{{-- Email Alert Component --}}
@props([
    'type' => 'info', // info, success, warning, error
    'title' => null,
    'icon' => true
])

@php
    $typeClasses = [
        'info' => 'email-alert email-alert-info',
        'success' => 'email-alert email-alert-success',
        'warning' => 'email-alert email-alert-warning',
        'error' => 'email-alert email-alert-error'
    ];

    $icons = [
        'info' => '&#8505;', // ℹ
        'success' => '&#10004;', // ✓
        'warning' => '&#9888;', // ⚠
        'error' => '&#10006;' // ✖
    ];

    $classes = $typeClasses[$type] ?? $typeClasses['info'];
    $iconSymbol = $icons[$type] ?? $icons['info'];
@endphp

<div class="{{ $classes }}">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            @if($icon)
                <td style="width: 24px; vertical-align: top; padding-right: 12px;">
                    <span style="font-size: 16px; font-weight: bold;">
                        {!! $iconSymbol !!}
                    </span>
                </td>
            @endif
            <td style="vertical-align: top;">
                @if($title)
                    <div style="font-weight: 600; margin-bottom: 4px;">
                        {{ $title }}
                    </div>
                @endif
                <div style="margin: 0;">
                    {{ $slot }}
                </div>
            </td>
        </tr>
    </table>
</div>


