@props(['value', 'label' => null])
@php
    $colors = [
        // device
        'available' => 'success', 'assigned' => 'primary', 'in_repair' => 'warning',
        'damaged' => 'danger', 'lost' => 'dark', 'retired' => 'secondary',
        // request
        'pending' => 'warning', 'approved' => 'info', 'rejected' => 'danger',
        'completed' => 'success', 'cancelled' => 'secondary',
        // priority
        'high' => 'danger', 'normal' => 'secondary', 'low' => 'light',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'badge status-pill text-bg-'.($colors[$value] ?? 'secondary')]) }}>{{ $label ?? ucwords(str_replace('_', ' ', $value)) }}</span>
