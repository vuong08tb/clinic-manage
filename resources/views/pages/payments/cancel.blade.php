@extends('layouts.app')

@section('title', 'Đã hủy thanh toán')

@section('content')
<div x-data="paymentCancelPage" class="mx-auto max-w-lg">
    <div class="surface-card p-8 text-center">
        <div x-show="loading" class="space-y-4" role="status">
            <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600"></div>
        </div>

        <div x-cloak x-show="!loading" class="space-y-4">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                <x-ui.icon name="ban" size="h-7 w-7" />
            </div>
            <h1 class="text-lg font-bold text-slate-900">Bạn đã hủy thanh toán</h1>
            <p class="text-sm text-slate-500">Không có khoản tiền nào bị trừ. Bạn có thể quay lại hóa đơn để thử
                thanh toán lại.</p>
            <x-ui.button href="/invoices" x-bind:href="invoiceUrl">Quay lại hóa đơn</x-ui.button>
        </div>
    </div>
</div>
@endsection
