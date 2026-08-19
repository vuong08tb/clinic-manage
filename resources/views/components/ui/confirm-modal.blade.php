@props([
    'show',
    'cancel',
    'confirm',
    'id' => 'confirm',
    'title' => 'Xác nhận thao tác',
    'titleExpr' => null,
    'variant' => 'danger',
    'confirmLabel' => 'Xác nhận',
    'busy' => 'false',
    'busyLabel' => 'Đang xử lý…',
])

{{-- Confirm popup for destructive or status-changing actions; sits above any open form/detail modal. --}}
<x-ui.modal :show="$show" :close="$cancel" :id="$id" :title="$title" :title-expr="$titleExpr" size="sm" z="60">
    <div class="space-y-2 text-sm leading-6 text-slate-600">
        {{ $slot }}
    </div>

    <x-slot:footer>
        <x-ui.button variant="secondary" x-on:click="{{ $cancel }}" x-bind:disabled="{{ $busy }}">
            Hủy
        </x-ui.button>

        <x-ui.button variant="{{ $variant }}" x-on:click="{{ $confirm }}" x-bind:disabled="{{ $busy }}">
            <span x-text="{{ $busy }} ? '{{ $busyLabel }}' : '{{ $confirmLabel }}'"></span>
        </x-ui.button>
    </x-slot:footer>
</x-ui.modal>
