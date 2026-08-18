@props([
    'icon' => 'dashboard',
    'title',
    'description' => null,
])

<div {{ $attributes->class('p-10 text-center') }}>
    <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-500">
        <x-ui.icon :name="$icon" />
    </span>
    <p class="mt-4 font-semibold text-slate-800">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
    @endif
    @if (isset($action))
        <div class="mt-4">{{ $action }}</div>
    @endif
</div>
