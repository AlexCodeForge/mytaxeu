{{-- Email Card Component --}}
@props([
    'type' => 'default', // default, highlight, success, warning, error
    'title' => null,
    'padding' => 'normal' // small, normal, large
])

@php
    $baseClasses = 'email-card';

    $typeClasses = [
        'default' => 'email-card',
        'highlight' => 'email-card email-card-highlight',
        'success' => 'email-card email-card-success',
        'warning' => 'email-card email-card-warning',
        'error' => 'email-card email-card-error'
    ];

    $paddingStyles = [
        'small' => 'padding: 12px !important;',
        'normal' => 'padding: 20px !important;',
        'large' => 'padding: 28px !important;'
    ];

    $classes = $typeClasses[$type] ?? $typeClasses['default'];
    $customStyles = $paddingStyles[$padding] ?? $paddingStyles['normal'];
@endphp

<div class="{{ $classes }}" style="{{ $customStyles }}">
    @if($title)
        <h3 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 600; color: #111827;">
            {{ $title }}
        </h3>
    @endif

    <div style="margin: 0;">
        {{ $slot }}
    </div>
</div>


