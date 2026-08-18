@props([
    'href' => null,
    'type' => 'button',
    'variant' => 'primary',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60';
    $variants = [
        'primary' => 'bg-blue-600 text-white shadow-sm hover:bg-blue-700',
        'secondary' => 'border border-slate-300 bg-white text-slate-700 shadow-sm hover:bg-slate-50',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700',
    ];
    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
