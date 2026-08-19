@props([
    'label' => null,
    'labelExpr' => null,
    'icon' => null,
    'tone' => 'primary',
    'toneExpr' => null,
])

@php
    // Base for every "Thao tác" button: icon only, label shown as a tooltip on hover/focus.
    // Below md the label stays inline and visible because touch devices have no hover state.
    $tones = [
        'primary' => 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100',
        'neutral' => 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
        'danger' => 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100',
    ];

    $base = 'group relative inline-flex items-center rounded-lg border px-2 py-1.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50';
    $classes = $base.($toneExpr ? '' : ' '.($tones[$tone] ?? $tones['primary']));

    // Alpine needs the tone map inline; json_encode would emit double quotes inside the attribute.
    $toneJs = '{'.collect($tones)->map(fn ($value, $key) => "'{$key}': '{$value}'")->implode(', ').'}';

    // Tooltip is anchored to the button's right edge: a centered one would stick out of the
    // table's `overflow-x-auto` wrapper and add a horizontal scrollbar even while invisible.
    $labelTooltip = 'ml-1.5 whitespace-nowrap'
        .' md:pointer-events-none md:absolute md:right-0 md:bottom-full md:z-20 md:mb-1.5 md:ml-0'
        .' md:rounded-md md:bg-slate-900 md:px-2 md:py-1 md:text-xs md:font-semibold md:text-white md:shadow-lg'
        .' md:opacity-0 md:transition-opacity md:duration-150'
        .' md:group-hover:opacity-100 md:group-focus-visible:opacity-100';
@endphp

<button type="button" @if ($toneExpr) x-bind:class="({{ $toneJs }})[{{ $toneExpr }}]" @endif
    @if ($labelExpr) x-bind:aria-label="{{ $labelExpr }}" @else aria-label="{{ $label }}" @endif
    {{ $attributes->class($classes) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" size="h-4 w-4" />
    @else
        {{ $slot }}
    @endif

    <span role="tooltip" class="{{ $labelTooltip }}" aria-hidden="true"
        @if ($labelExpr) x-text="{{ $labelExpr }}" @endif>{{ $labelExpr ? '' : $label }}</span>
</button>
