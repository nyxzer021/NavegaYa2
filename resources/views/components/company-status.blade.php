@props(['status'])
@php
    $labels = [
        'scheduled' => 'Programada',
        'boarding' => 'Embarque abierto',
        'in_transit' => 'En tránsito',
        'departed' => 'En tránsito',
        'completed' => 'Completada',
        'delayed' => 'Retrasada',
        'rescheduled' => 'Reprogramada',
        'suspended' => 'Suspendida',
        'cancelled' => 'Cancelada',
    ];
    $colors = [
        'scheduled' => 'bg-amber-50 text-amber-900',
        'boarding' => 'bg-emerald-100 text-emerald-900',
        'in_transit' => 'bg-[#062c21] text-white',
        'departed' => 'bg-[#062c21] text-white',
        'completed' => 'bg-slate-100 text-slate-600',
        'delayed' => 'bg-orange-100 text-orange-900',
        'rescheduled' => 'bg-amber-50 text-amber-900',
        'suspended' => 'bg-orange-100 text-orange-900',
        'cancelled' => 'bg-rose-100 text-rose-800',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold '.($colors[$status] ?? 'bg-slate-100 text-slate-700')]) }}>{{ $labels[$status] ?? \App\Support\PanelLabel::status($status) }}</span>
