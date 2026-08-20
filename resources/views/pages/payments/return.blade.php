@extends('layouts.app')

@section('title', 'Kết quả thanh toán')

@section('content')
<div x-data="paymentReturnPage" class="mx-auto max-w-lg">
    <div class="surface-card p-8 text-center">
        <div x-show="status === 'loading'" class="space-y-4" role="status" aria-label="Đang xác nhận thanh toán">
            <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600"></div>
            <p class="font-semibold text-slate-700">Đang xác nhận thanh toán với PayPal…</p>
        </div>

        <div x-cloak x-show="status === 'success'" class="space-y-4">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                <x-ui.icon name="check" size="h-7 w-7" />
            </div>
            <h1 class="text-lg font-bold text-slate-900">Thanh toán thành công</h1>
            <p class="text-sm text-slate-500" x-show="payment"
                x-text="`Số tiền: ${payment ? new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(payment.amount) : ''}`">
            </p>
            <x-ui.button href="/invoices" x-bind:href="invoiceUrl">Quay lại hóa đơn</x-ui.button>
        </div>

        <div x-cloak x-show="status === 'failed'" class="space-y-4">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                <x-ui.icon name="ban" size="h-7 w-7" />
            </div>
            <h1 class="text-lg font-bold text-slate-900">Thanh toán không thành công</h1>
            <p class="text-sm text-slate-500">PayPal chưa hoàn tất giao dịch này. Bạn có thể quay lại hóa đơn và thử
                lại.</p>
            <x-ui.button href="/invoices" x-bind:href="invoiceUrl" variant="secondary">Quay lại hóa đơn</x-ui.button>
        </div>

        <div x-cloak x-show="status === 'not_found' || status === 'error'" class="space-y-4">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                <x-ui.icon name="ban" size="h-7 w-7" />
            </div>
            <h1 class="text-lg font-bold text-slate-900">Không thể xác nhận thanh toán</h1>
            <p class="text-sm text-slate-500" x-text="message"></p>
            <x-ui.button href="/invoices" variant="secondary">Về danh sách hóa đơn</x-ui.button>
        </div>
    </div>
</div>
@endsection
