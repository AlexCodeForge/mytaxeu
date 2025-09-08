{{-- Email Button Component --}}
@props([
    'url' => '#',
    'type' => 'primary', // primary, secondary, accent, success, warning, error
    'size' => 'medium', // small, medium, large
    'fullWidth' => false
])

@php
    $baseClasses = 'email-button';

    $typeClasses = [
        'primary' => 'email-button',
        'secondary' => 'email-button email-button-secondary',
        'accent' => 'email-button email-button-accent',
        'success' => 'email-button',
        'warning' => 'email-button',
        'error' => 'email-button'
    ];

    $sizeStyles = [
        'small' => 'padding: 10px 20px !important; font-size: 14px !important;',
        'medium' => 'padding: 14px 28px !important; font-size: 16px !important;',
        'large' => 'padding: 18px 36px !important; font-size: 18px !important;'
    ];

    $typeStyles = [
        'success' => 'background-color: #10b981 !important; color: #ffffff !important;',
        'warning' => 'background-color: #fbbf24 !important; color: #111827 !important;',
        'error' => 'background-color: #ef4444 !important; color: #ffffff !important;'
    ];

    $classes = $typeClasses[$type] ?? $typeClasses['primary'];
    $customStyles = ($sizeStyles[$size] ?? $sizeStyles['medium']);

    if (isset($typeStyles[$type])) {
        $customStyles .= ' ' . $typeStyles[$type];
    }

    if ($fullWidth) {
        $customStyles .= ' display: block !important; width: 100% !important; text-align: center !important;';
    }
@endphp

<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 16px 0;">
    <tr>
        <td style="text-align: {{ $fullWidth ? 'center' : 'left' }};">
            <a href="{{ $url }}"
               class="{{ $classes }}"
               style="{{ $customStyles }}"
               target="_blank">
                {{ $slot }}
            </a>
        </td>
    </tr>
</table>


