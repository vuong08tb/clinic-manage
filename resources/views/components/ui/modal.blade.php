@props([
    'show',
    'close' => null,
    'id' => 'modal',
    'title' => null,
    'titleExpr' => null,
    'subtitleExpr' => null,
    'size' => 'lg',
    'z' => '50',
])

@php
    // Every add/edit/view flow uses this shell so the dialog chrome stays identical across features.
    $widths = [
        'sm' => 'max-w-md',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
    ];
    $width = $widths[$size] ?? $widths['lg'];

    // Static map: Tailwind cannot generate a utility class assembled from a Blade variable.
    $layers = [
        '50' => 'z-50',
        '60' => 'z-[60]',
    ];
    $layer = $layers[(string) $z] ?? $layers['50'];
@endphp

<div x-cloak x-show="{{ $show }}" class="fixed inset-0 {{ $layer }} overflow-y-auto" role="dialog" aria-modal="true"
    aria-labelledby="{{ $id }}-title">
    <div x-show="{{ $show }}" x-transition.opacity class="fixed inset-0 bg-slate-950/40"
        @if ($close) x-on:click="{{ $close }}" @endif></div>

    <div class="flex min-h-full items-start justify-center p-4 sm:items-center">
        <div x-show="{{ $show }}" x-transition
            {{ $attributes->class('surface-card relative flex w-full '.$width.' flex-col overflow-hidden') }}>
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div class="min-w-0">
                    <h2 id="{{ $id }}-title" class="text-lg font-bold text-slate-900"
                        @if ($titleExpr) x-text="{{ $titleExpr }}" @endif>{{ $titleExpr ? '' : $title }}</h2>

                    @if ($subtitleExpr)
                        <p class="mt-1 text-sm text-slate-500" x-text="{{ $subtitleExpr }}"></p>
                    @elseif (isset($subtitle))
                        <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
                    @endif
                </div>

                @if ($close)
                    <button type="button"
                        class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"
                        x-on:click="{{ $close }}" aria-label="Đóng">
                        <x-ui.icon name="close" />
                    </button>
                @endif
            </div>

            <div class="max-h-[70vh] overflow-y-auto px-5 py-5">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 px-5 py-4">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
