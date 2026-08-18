@props(['title', 'description' => null])

<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1.5 text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
    @endif
</div>
