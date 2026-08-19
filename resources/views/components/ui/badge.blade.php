@props([
    'tone' => 'slate',
])

@php
    $tones = [
        'slate' => 'bg-slate-50 text-slate-700 ring-slate-600/20',
        'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'teal' => 'bg-teal-50 text-teal-700 ring-teal-600/20',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    ];
@endphp

<span {{ $attributes->class('inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset '.($tones[$tone] ?? $tones['slate'])) }}>
    {{ $slot }}
</span>
