@extends('layouts.app')

@section('title', 'Hóa đơn')

@section('content')
<div x-data="invoiceIndexPage" x-on:keydown.escape.window="handleEscape()"
    class="mx-auto max-w-[1400px] space-y-6">
    <x-layout.page-header title="Hóa đơn" description="Hóa đơn lập từ phiếu khám, theo dõi thanh toán qua PayPal.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="refresh()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />
                <span x-text="refreshing ? 'Đang tải' : 'Làm mới'"></span>
            </x-ui.button>

            <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">
                <x-ui.icon name="invoice" />
                Tạo hóa đơn
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    {{-- Filters --}}
    <section class="surface-card p-4 sm:p-5">
        <div class="sm:w-56">
            <label for="invoice-status" class="mb-2 block text-sm font-semibold text-slate-700">Trạng thái</label>
            <select id="invoice-status" class="form-input" x-model="filters.status" x-on:change="applyFilters()">
                <option value="">Tất cả</option>
                <option value="unpaid">Chưa thanh toán</option>
                <option value="paid">Đã thanh toán</option>
                <option value="cancelled">Đã hủy</option>
            </select>
        </div>
    </section>

    <section x-show="loading" class="surface-card space-y-3 p-6" role="status">
        <template x-for="index in 6" :key="index">
            <div class="h-14 animate-pulse rounded-xl bg-slate-100"></div>
        </template>
    </section>

    <section x-cloak x-show="!loading && listError" class="surface-card p-10 text-center">
        <p class="font-semibold text-rose-700">Không thể tải danh sách hóa đơn</p>
        <p class="mt-2 text-sm text-slate-500" x-text="listError"></p>
        <x-ui.button variant="secondary" class="mt-5" x-on:click="loadList()">Thử lại</x-ui.button>
    </section>

    <section x-cloak x-show="!loading && !listError && invoices.length === 0" class="surface-card">
        <x-ui.empty-state icon="invoice" title="Không tìm thấy hóa đơn"
            description="Hãy thử bộ lọc khác hoặc tạo hóa đơn mới từ một phiếu khám.">
            <x-slot:action>
                <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">Tạo hóa đơn</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    </section>

    <section x-cloak x-show="!loading && !listError && invoices.length > 0" class="surface-card overflow-hidden">
        {{-- Mobile cards --}}
        <div class="divide-y divide-slate-100 md:hidden">
            <template x-for="invoice in invoices" :key="invoice.id">
                <article class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-blue-600" x-text="invoice.invoice_code"></p>
                            <h3 class="mt-1 truncate font-bold text-slate-900"
                                x-text="invoice.examination?.patient?.full_name ?? '—'"></h3>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                            x-bind:class="statusClasses(invoice.status)" x-text="statusLabel(invoice.status)"></span>
                    </div>

                    <p class="text-sm font-semibold text-slate-700" x-text="formatCurrency(invoice.total)"></p>
                    <p class="text-xs text-slate-500" x-text="formatDate(invoice.issued_at, { dateStyle: 'medium' })">
                    </p>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                        <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                            x-on:click="openDetailModal(invoice)" />
                        <x-ui.row-action label="Sửa" icon="edit" tone="neutral"
                            x-show="canUpdate && invoice.status === 'unpaid'" x-on:click="openEditModal(invoice)" />
                        <x-ui.row-action label="Hủy hóa đơn" icon="ban" tone="danger"
                            x-show="canUpdateStatus && invoice.status === 'unpaid'" x-on:click="askCancel(invoice)" />
                    </div>
                </article>
            </template>
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <th class="px-5 py-3">Mã hóa đơn</th>
                        <th class="px-5 py-3">Bệnh nhân</th>
                        <th class="px-5 py-3">Bác sĩ</th>
                        <th class="px-5 py-3">Tổng tiền</th>
                        <th class="px-5 py-3">Trạng thái</th>
                        <th class="px-5 py-3">Ngày lập</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="invoice in invoices" :key="invoice.id">
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-blue-700"
                                x-text="invoice.invoice_code"></td>
                            <td class="px-5 py-4 text-sm text-slate-700"
                                x-text="invoice.examination?.patient?.full_name ?? '—'"></td>
                            <td class="px-5 py-4 text-sm text-slate-700"
                                x-text="invoice.examination?.doctor?.user?.name ?? '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-900"
                                x-text="formatCurrency(invoice.total)"></td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    x-bind:class="statusClasses(invoice.status)" x-text="statusLabel(invoice.status)"></span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600"
                                x-text="formatDate(invoice.issued_at, { dateStyle: 'medium' })"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                                        x-on:click="openDetailModal(invoice)" />
                                    <x-ui.row-action label="Sửa" icon="edit" tone="neutral"
                                        x-show="canUpdate && invoice.status === 'unpaid'"
                                        x-on:click="openEditModal(invoice)" />
                                    <x-ui.row-action label="Hủy hóa đơn" icon="ban" tone="danger"
                                        x-show="canUpdateStatus && invoice.status === 'unpaid'"
                                        x-on:click="askCancel(invoice)" />
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div x-show="meta.total > 0"
            class="flex flex-col gap-4 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500"
                x-text="`Hiển thị ${meta.from ?? 0}–${meta.to ?? 0} trong ${meta.total} hóa đơn`"></p>
            <div class="flex flex-wrap items-center gap-1">
                <button type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                    x-bind:disabled="meta.current_page <= 1" x-on:click="goToPage(meta.current_page - 1)">
                    Trước
                </button>
                <template x-for="page in visiblePages" :key="page">
                    <button type="button" class="rounded-lg border px-3 py-2 text-sm" x-bind:class="{
                                'border-blue-600 bg-blue-600 text-white': page === meta.current_page,
                                'border-slate-300 bg-white text-slate-700': page !== meta.current_page
                            }" x-on:click="goToPage(page)" x-text="page"></button>
                </template>
                <button type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                    x-bind:disabled="meta.current_page >= meta.last_page" x-on:click="goToPage(meta.current_page + 1)">
                    Sau
                </button>
            </div>
        </div>
    </section>

    {{-- Detail modal --}}
    <x-ui.modal show="detailOpen" close="closeDetailModal()" id="invoice-detail" title="Chi tiết hóa đơn"
        subtitle-expr="detail?.invoice_code ? `Mã hóa đơn: ${detail.invoice_code}` : ''" size="xl">
        <div x-show="detailLoading" class="space-y-3" role="status" aria-label="Đang tải hóa đơn">
            <div class="h-5 w-40 animate-pulse rounded bg-slate-100"></div>
            <div class="h-24 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <p x-cloak x-show="detailError" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            role="alert" x-text="detailError"></p>

        <div x-cloak x-show="!detailLoading && !detailError && detail" class="space-y-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-bold text-slate-900" x-text="detail?.examination?.patient?.full_name ?? '—'"></p>
                    <p class="text-sm text-slate-500" x-text="`BS. ${detail?.examination?.doctor?.user?.name ?? '—'}`"></p>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                    x-bind:class="statusClasses(detail?.status)" x-text="statusLabel(detail?.status)"></span>
            </div>

            {{-- Chi tiết khoản thu --}}
            <div class="rounded-xl border border-slate-200 p-4">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Chi tiết khoản thu</h3>

                <div x-show="(detail?.items ?? []).length > 0" class="mb-3 space-y-1.5 border-b border-slate-100 pb-3">
                    <template x-for="item in detail?.items ?? []" :key="item.id">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-600"
                                x-text="`${item.medicine?.name ?? '—'} × ${item.quantity}`"></span>
                            <span class="text-slate-700"
                                x-text="formatCurrency(item.quantity * (item.medicine?.price ?? 0))"></span>
                        </div>
                    </template>
                </div>

                <dl class="space-y-1.5 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Phí khám + tiền thuốc (tạm tính)</dt>
                        <dd class="text-slate-700" x-text="formatCurrency(detail?.subtotal)"></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Giảm giá</dt>
                        <dd class="text-rose-600" x-text="`- ${formatCurrency(detail?.discount)}`"></dd>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-1.5 font-semibold">
                        <dt class="text-slate-900">Tổng cộng</dt>
                        <dd class="text-slate-900" x-text="formatCurrency(detail?.total)"></dd>
                    </div>
                    <div class="flex items-center justify-between text-emerald-700">
                        <dt>Đã thanh toán</dt>
                        <dd x-text="formatCurrency(completedPaymentsTotal)"></dd>
                    </div>
                    <div class="flex items-center justify-between font-semibold text-amber-700">
                        <dt>Còn lại</dt>
                        <dd x-text="formatCurrency(remainingBalance)"></dd>
                    </div>
                </dl>
            </div>

            {{-- Lịch sử thanh toán --}}
            <div>
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Lịch sử thanh toán</h3>

                <div x-show="paymentsLoading" class="text-sm text-slate-500">Đang tải…</div>
                <p x-cloak x-show="paymentsError" class="text-sm text-rose-600" x-text="paymentsError"></p>

                <div x-show="!paymentsLoading && !paymentsError" class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-2 text-left">Số tiền</th>
                                <th class="px-4 py-2 text-left">Phương thức</th>
                                <th class="px-4 py-2 text-left">Trạng thái</th>
                                <th class="px-4 py-2 text-left">Ngày</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-if="payments.length === 0">
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có giao dịch nào.</td>
                                </tr>
                            </template>
                            <template x-for="payment in payments" :key="payment.id">
                                <tr>
                                    <td class="px-4 py-2 font-semibold text-slate-900" x-text="formatCurrency(payment.amount)"></td>
                                    <td class="px-4 py-2 text-slate-600" x-text="payment.method === 'visa' ? 'Visa' : 'PayPal'"></td>
                                    <td class="px-4 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                            x-bind:class="statusClasses(payment.status)" x-text="statusLabel(payment.status)"></span>
                                    </td>
                                    <td class="px-4 py-2 text-slate-500" x-text="formatDate(payment.paid_at ?? payment.created_at, { dateStyle: 'short' })"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tạo thanh toán mới --}}
            <div x-show="detail?.status === 'unpaid' && remainingBalance > 0 && canCreatePayment"
                class="space-y-3 rounded-xl border border-dashed border-slate-300 p-4">
                <p class="text-sm font-semibold text-slate-700">Tạo thanh toán</p>

                <p x-cloak x-show="paymentMessage" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"
                    role="alert" x-text="paymentMessage"></p>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Phương thức</label>
                        <select class="form-input" x-model="paymentForm.method">
                            <option value="paypal">PayPal</option>
                            <option value="visa">Visa (qua PayPal)</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Số tiền</label>
                        <input type="number" min="1" class="form-input" x-model="paymentForm.amount"
                            x-bind:placeholder="remainingBalance">
                    </div>
                    <div class="flex items-end">
                        <x-ui.button type="button" x-on:click="submitPayment()" x-bind:disabled="paymentSubmitting"
                            class="w-full">
                            <span x-show="paymentSubmitting"
                                class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                                aria-hidden="true"></span>
                            <span x-text="paymentSubmitting ? 'Đang chuyển…' : 'Thanh toán qua PayPal'"></span>
                            <x-ui.icon name="arrow-right" size="h-4 w-4" />
                        </x-ui.button>
                    </div>
                </div>

                <p class="text-xs text-slate-400">
                    Bạn sẽ được chuyển sang trang PayPal để hoàn tất thanh toán, sau đó quay lại đây.
                </p>
            </div>
        </div>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeDetailModal()">Đóng</x-ui.button>

            <x-ui.button variant="danger" x-show="canUpdateStatus && detail?.status === 'unpaid'"
                x-on:click="askCancel(detail)">
                Hủy hóa đơn
            </x-ui.button>

            <x-ui.button x-show="canUpdate && detail?.status === 'unpaid'" x-on:click="editFromDetail()">
                Sửa giảm giá
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Create/edit form modal --}}
    <x-ui.modal show="formOpen" close="closeFormModal()" id="invoice-form-modal"
        title-expr="formMode === 'create' ? 'Tạo hóa đơn' : 'Cập nhật hóa đơn'"
        subtitle-expr="formMode === 'create' ? 'Tạo hóa đơn từ một phiếu khám.' : 'Chỉ có thể sửa giảm giá khi hóa đơn chưa thanh toán.'">
        <form id="invoice-form" x-on:submit.prevent="submitForm()" novalidate class="space-y-5">
            <div x-cloak x-show="formMessage"
                class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"
                x-text="formMessage"></div>

            <div x-show="formMode === 'create'">
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Phiếu khám <span class="text-rose-600">*</span>
                </label>

                <div x-show="preselecting" class="text-sm text-slate-500">Đang tải phiếu khám…</div>

                <div x-show="!preselecting && form.examination_id"
                    class="flex items-center justify-between rounded-xl border border-slate-300 px-3 py-2.5">
                    <span class="text-sm text-slate-800" x-text="form.examination_label"></span>
                    <button type="button" class="text-xs font-semibold text-rose-600" x-on:click="clearExamination()">
                        Đổi
                    </button>
                </div>

                <div x-show="!preselecting && !form.examination_id" class="space-y-3">
                    <div class="relative">
                        <input type="text" class="form-input" placeholder="Tìm bệnh nhân theo tên, SĐT hoặc mã"
                            x-model="examinationPickerPatientQuery"
                            x-on:input.debounce.350ms="searchExaminationPickerPatients()">

                        <ul x-show="examinationPickerPatientResults.length > 0"
                            class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                            <template x-for="patient in examinationPickerPatientResults" :key="patient.id">
                                <li>
                                    <button type="button" x-on:click="pickExaminationPickerPatient(patient)"
                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">
                                        <span class="font-semibold" x-text="patient.full_name"></span>
                                        <span class="ml-1 text-xs text-slate-500" x-text="`(${patient.code})`"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div x-show="examinationPickerLoading" class="text-sm text-slate-500">Đang tải phiếu khám…</div>

                    <ul x-show="!examinationPickerLoading && examinationPickerExaminations.length > 0"
                        class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-2">
                        <template x-for="examination in examinationPickerExaminations" :key="examination.id">
                            <li>
                                <button type="button" x-on:click="selectExamination(examination)"
                                    class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50">
                                    <span class="font-semibold"
                                        x-text="formatDate(examination.examined_at, { dateStyle: 'medium' })"></span>
                                    <span class="ml-1 text-xs text-slate-500"
                                        x-text="`· BS. ${examination.doctor?.user?.name ?? '—'}`"></span>
                                    <p class="truncate text-xs text-slate-500" x-text="examination.diagnosis"></p>
                                </button>
                            </li>
                        </template>
                    </ul>
                </div>

                <p x-cloak x-show="fieldError('examination_id')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('examination_id')"></p>
                <p x-cloak x-show="fieldError('examination')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('examination')"></p>
            </div>

            <div>
                <label for="invoice-discount" class="mb-2 block text-sm font-semibold text-slate-700">
                    Giảm giá (VND)
                </label>
                <input id="invoice-discount" type="number" min="0" step="1" class="form-input"
                    x-model="form.discount" x-bind:class="{ 'form-input-error': fieldError('discount') }">
                <p x-cloak x-show="fieldError('discount')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('discount')"></p>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeFormModal()" x-bind:disabled="submitting">Hủy</x-ui.button>

            <x-ui.button type="submit" form="invoice-form" x-bind:disabled="submitting">
                <span x-show="submitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    aria-hidden="true"></span>
                <span x-text="submitting ? 'Đang lưu…' : 'Lưu hóa đơn'"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Confirm popup: hủy hóa đơn --}}
    <x-ui.confirm-modal show="cancelTarget" cancel="cancelDismiss()" confirm="confirmCancel()" id="invoice-cancel"
        title="Hủy hóa đơn?" busy="cancelling" confirm-label="Hủy hóa đơn" busy-label="Đang hủy…">
        <p>
            Hóa đơn <strong x-text="cancelTarget?.invoice_code"></strong> sẽ chuyển sang trạng thái đã hủy và không
            thể tạo thanh toán mới.
        </p>
    </x-ui.confirm-modal>
</div>
@endsection
