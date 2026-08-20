@extends('layouts.app')

@section('title', 'Kho thuốc')

@section('content')
<div x-data="medicineIndexPage" x-on:keydown.escape.window="handleEscape()"
    class="mx-auto max-w-[1600px] space-y-6">
    <x-layout.page-header title="Kho thuốc" description="Danh mục thuốc, tồn kho và điều chỉnh nhập/xuất.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="refresh()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />
                <span x-text="refreshing ? 'Đang tải' : 'Làm mới'"></span>
            </x-ui.button>

            <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">
                <x-ui.icon name="medicine" />
                Thêm thuốc
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    {{-- Filters --}}
    <section class="surface-card p-4 sm:p-5">
        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="flex-1">
                <label for="medicine-search" class="mb-2 block text-sm font-semibold text-slate-700">
                    Tìm kiếm
                </label>
                <input id="medicine-search" type="search" class="form-input" placeholder="Tên hoặc mã thuốc"
                    maxlength="255" x-model="filters.q" x-on:input.debounce.350ms="search()">
            </div>

            <div class="sm:w-48">
                <label for="medicine-stock-status" class="mb-2 block text-sm font-semibold text-slate-700">
                    Tồn kho
                </label>
                <select id="medicine-stock-status" class="form-input" x-model="filters.stock_status"
                    x-on:change="applyFilters()">
                    <option value="">Tất cả</option>
                    <option value="in_stock">Còn hàng</option>
                    <option value="out_of_stock">Hết hàng</option>
                </select>
            </div>

            <div class="sm:w-40">
                <label for="medicine-per-page" class="mb-2 block text-sm font-semibold text-slate-700">
                    Số dòng
                </label>
                <select id="medicine-per-page" class="form-input" x-model.number="filters.per_page"
                    x-on:change="changePerPage()">
                    <option value="10">10 dòng</option>
                    <option value="15">15 dòng</option>
                    <option value="25">25 dòng</option>
                    <option value="50">50 dòng</option>
                </select>
            </div>
        </div>
    </section>

    <section x-show="loading" class="surface-card space-y-3 p-6" role="status" aria-label="Đang tải danh sách thuốc">
        <template x-for="index in 6" :key="index">
            <div class="h-14 animate-pulse rounded-xl bg-slate-100"></div>
        </template>
    </section>

    <section x-cloak x-show="!loading && listError" class="surface-card p-10 text-center">
        <p class="font-semibold text-rose-700">Không thể tải danh sách thuốc</p>
        <p class="mt-2 text-sm text-slate-500" x-text="listError"></p>
        <x-ui.button variant="secondary" class="mt-5" x-on:click="loadMedicines()">Thử lại</x-ui.button>
    </section>

    <section x-cloak x-show="!loading && !listError && medicines.length === 0" class="surface-card">
        <x-ui.empty-state icon="medicine" title="Không tìm thấy thuốc"
            description="Hãy thử bộ lọc khác hoặc thêm thuốc mới vào kho.">
            <x-slot:action>
                <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">Thêm thuốc</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    </section>

    <section x-cloak x-show="!loading && !listError && medicines.length > 0" class="surface-card overflow-hidden">
        {{-- Mobile cards --}}
        <div class="divide-y divide-slate-100 md:hidden">
            <template x-for="medicine in medicines" :key="medicine.id">
                <article class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-blue-600" x-text="medicine.code"></p>
                            <h3 class="mt-1 truncate font-bold text-slate-900" x-text="medicine.name"></h3>
                            <p class="text-xs text-slate-500" x-text="`Đơn vị: ${medicine.unit}`"></p>
                        </div>

                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                            x-bind:class="statusClasses(medicine.is_active ? 'active' : 'inactive')"
                            x-text="statusLabel(medicine.is_active ? 'active' : 'inactive')"></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                            x-bind:class="stockClasses(medicine.stock)" x-text="stockLabel(medicine.stock)"></span>
                        <span class="text-sm font-semibold text-slate-700" x-text="formatCurrency(medicine.price)"></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                        <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                            x-on:click="openDetailModal(medicine)" />

                        <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                            x-on:click="openEditModal(medicine)" />

                        <x-ui.row-action label="Điều chỉnh tồn kho" icon="swap" tone="neutral"
                            x-show="canAdjustStock" x-on:click="askAdjustStock(medicine)" />

                        <x-ui.row-action label="Xóa" icon="trash" tone="danger" x-show="canDelete"
                            x-on:click="askDelete(medicine)" />
                    </div>
                </article>
            </template>
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <th class="px-5 py-3">Mã</th>
                        <th class="px-5 py-3">Tên thuốc</th>
                        <th class="px-5 py-3">Đơn giá</th>
                        <th class="px-5 py-3">Tồn kho</th>
                        <th class="px-5 py-3">Trạng thái</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="medicine in medicines" :key="medicine.id">
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-blue-700"
                                x-text="medicine.code"></td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900" x-text="medicine.name"></p>
                                <p class="mt-1 text-xs text-slate-500" x-text="`Đơn vị: ${medicine.unit}`"></p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700"
                                x-text="formatCurrency(medicine.price)"></td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    x-bind:class="stockClasses(medicine.stock)" x-text="stockLabel(medicine.stock)"></span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    x-bind:class="statusClasses(medicine.is_active ? 'active' : 'inactive')"
                                    x-text="statusLabel(medicine.is_active ? 'active' : 'inactive')"></span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                                        x-on:click="openDetailModal(medicine)" />

                                    <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                                        x-on:click="openEditModal(medicine)" />

                                    <x-ui.row-action label="Điều chỉnh tồn kho" icon="swap" tone="neutral"
                                        x-show="canAdjustStock" x-on:click="askAdjustStock(medicine)" />

                                    <x-ui.row-action label="Xóa" icon="trash" tone="danger" x-show="canDelete"
                                        x-on:click="askDelete(medicine)" />
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
                x-text="`Hiển thị ${meta.from ?? 0}–${meta.to ?? 0} trong ${meta.total} thuốc`"></p>
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
    <x-ui.modal show="detailOpen" close="closeDetailModal()" id="medicine-detail" title="Chi tiết thuốc"
        subtitle-expr="detail?.code ? `Mã thuốc: ${detail.code}` : ''">
        <div x-show="detailLoading" class="space-y-3" role="status" aria-label="Đang tải thông tin thuốc">
            <div class="h-5 w-40 animate-pulse rounded bg-slate-100"></div>
            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <p x-cloak x-show="detailError" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            role="alert" x-text="detailError"></p>

        <dl x-cloak x-show="!detailLoading && !detailError && detail" class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-slate-500">Tên thuốc</dt>
                <dd class="mt-1 text-lg font-bold text-slate-900" x-text="detail?.name"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Đơn vị</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.unit"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Đơn giá</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="formatCurrency(detail?.price)"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Tồn kho</dt>
                <dd class="mt-1">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                        x-bind:class="stockClasses(detail?.stock)" x-text="stockLabel(detail?.stock)"></span>
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Trạng thái</dt>
                <dd class="mt-1">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                        x-bind:class="statusClasses(detail?.is_active ? 'active' : 'inactive')"
                        x-text="statusLabel(detail?.is_active ? 'active' : 'inactive')"></span>
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Ngày tạo</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="formatDate(detail?.created_at)"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Cập nhật gần nhất</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="formatDate(detail?.updated_at)"></dd>
            </div>
        </dl>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeDetailModal()">Đóng</x-ui.button>

            <x-ui.button variant="secondary" x-show="canAdjustStock" x-on:click="askAdjustStock(detail)">
                Điều chỉnh tồn kho
            </x-ui.button>

            <x-ui.button x-show="canUpdate" x-on:click="editFromDetail()">Sửa thuốc</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Create/update modal --}}
    <x-ui.modal show="formOpen" close="closeFormModal()" id="medicine-form-modal"
        title-expr="formMode === 'create' ? 'Thêm thuốc' : 'Cập nhật thuốc'"
        subtitle-expr="formMode === 'edit' ? `Mã thuốc: ${editingCode}` : 'Số lượng tồn kho ban đầu khi thêm mới; sau đó điều chỉnh qua chức năng riêng.'">
        <form id="medicine-form" x-on:submit.prevent="submitForm()" novalidate>
            <div x-cloak x-show="formMessage"
                class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"
                x-text="formMessage"></div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="medicine-code" class="mb-2 block text-sm font-semibold text-slate-700">
                        Mã thuốc <span class="text-rose-600">*</span>
                    </label>
                    <input id="medicine-code" type="text" class="form-input" maxlength="50" required
                        x-model.trim="form.code" x-bind:class="{ 'form-input-error': fieldError('code') }">
                    <p x-cloak x-show="fieldError('code')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('code')"></p>
                </div>

                <div>
                    <label for="medicine-unit" class="mb-2 block text-sm font-semibold text-slate-700">
                        Đơn vị <span class="text-rose-600">*</span>
                    </label>
                    <input id="medicine-unit" type="text" class="form-input" maxlength="50" required
                        placeholder="Viên, hộp, chai…" x-model.trim="form.unit"
                        x-bind:class="{ 'form-input-error': fieldError('unit') }">
                    <p x-cloak x-show="fieldError('unit')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('unit')"></p>
                </div>

                <div class="sm:col-span-2">
                    <label for="medicine-name" class="mb-2 block text-sm font-semibold text-slate-700">
                        Tên thuốc <span class="text-rose-600">*</span>
                    </label>
                    <input id="medicine-name" type="text" class="form-input" maxlength="255" required
                        x-model.trim="form.name" x-bind:class="{ 'form-input-error': fieldError('name') }">
                    <p x-cloak x-show="fieldError('name')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('name')"></p>
                </div>

                <div>
                    <label for="medicine-price" class="mb-2 block text-sm font-semibold text-slate-700">
                        Đơn giá (VND) <span class="text-rose-600">*</span>
                    </label>
                    <input id="medicine-price" type="number" min="0" step="1" class="form-input" required
                        x-model="form.price" x-bind:class="{ 'form-input-error': fieldError('price') }">
                    <p x-cloak x-show="fieldError('price')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('price')"></p>
                </div>

                <div x-show="formMode === 'create'">
                    <label for="medicine-stock" class="mb-2 block text-sm font-semibold text-slate-700">
                        Tồn kho ban đầu <span class="text-rose-600">*</span>
                    </label>
                    <input id="medicine-stock" type="number" min="0" step="1" class="form-input"
                        x-bind:required="formMode === 'create'" x-model="form.stock"
                        x-bind:class="{ 'form-input-error': fieldError('stock') }">
                    <p x-cloak x-show="fieldError('stock')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('stock')"></p>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" class="h-4 w-4 rounded border-slate-300" x-model="form.is_active">
                        Đang bán (hiển thị cho bác sĩ khi kê toa)
                    </label>
                    <p x-cloak x-show="fieldError('is_active')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('is_active')"></p>
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeFormModal()" x-bind:disabled="submitting">Hủy</x-ui.button>

            <x-ui.button type="submit" form="medicine-form" x-bind:disabled="submitting">
                <span x-show="submitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    aria-hidden="true"></span>
                <span x-text="submitting ? 'Đang lưu…' : 'Lưu thuốc'"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Adjust stock modal --}}
    <x-ui.modal show="stockOpen" close="closeStockModal()" id="medicine-stock-modal" title="Điều chỉnh tồn kho"
        subtitle-expr="stockTarget?.name ? `${stockTarget.name} · Tồn hiện tại: ${stockTarget.stock}` : ''" size="md">
        <form id="medicine-stock-form" x-on:submit.prevent="submitStockAdjust()" novalidate class="space-y-5">
            <div x-cloak x-show="stockMessage"
                class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"
                x-text="stockMessage"></div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Loại điều chỉnh</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold"
                        x-bind:class="stockForm.direction === 'in'
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                            : 'border-slate-300 text-slate-600'">
                        <input type="radio" class="sr-only" value="in" x-model="stockForm.direction">
                        Nhập thêm
                    </label>
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold"
                        x-bind:class="stockForm.direction === 'out'
                            ? 'border-rose-300 bg-rose-50 text-rose-700'
                            : 'border-slate-300 text-slate-600'">
                        <input type="radio" class="sr-only" value="out" x-model="stockForm.direction">
                        Xuất bớt
                    </label>
                </div>
            </div>

            <div>
                <label for="medicine-stock-amount" class="mb-2 block text-sm font-semibold text-slate-700">
                    Số lượng <span class="text-rose-600">*</span>
                </label>
                <input id="medicine-stock-amount" type="number" min="1" step="1" class="form-input" required
                    x-model="stockForm.amount" x-bind:class="{
                            'form-input-error': stockFieldError('quantity') || stockGoesNegative
                        }">
                <p x-cloak x-show="stockGoesNegative" class="mt-1.5 text-sm text-rose-600">
                    Số lượng xuất vượt quá tồn kho hiện có.
                </p>
                <p x-cloak x-show="!stockGoesNegative && stockFieldError('quantity')" class="mt-1.5 text-sm text-rose-600"
                    x-text="stockFieldError('quantity')"></p>
                <p x-show="stockForm.amount !== ''" class="mt-1.5 text-sm text-slate-500"
                    x-text="`Tồn kho sau điều chỉnh: ${stockResultingAmount}`"></p>
            </div>

            <div>
                <label for="medicine-stock-note" class="mb-2 block text-sm font-semibold text-slate-700">
                    Lý do điều chỉnh
                </label>
                <textarea id="medicine-stock-note" rows="3" class="form-input" maxlength="255"
                    placeholder="Ví dụ: nhập hàng từ nhà cung cấp, hủy do hết hạn…" x-model.trim="stockForm.note"
                    x-bind:class="{ 'form-input-error': stockFieldError('note') }"></textarea>
                <p x-cloak x-show="stockFieldError('note')" class="mt-1.5 text-sm text-rose-600"
                    x-text="stockFieldError('note')"></p>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeStockModal()" x-bind:disabled="stockSubmitting">
                Hủy
            </x-ui.button>

            <x-ui.button type="submit" form="medicine-stock-form" x-bind:disabled="stockSubmitting">
                <span x-show="stockSubmitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    aria-hidden="true"></span>
                <span x-text="stockSubmitting ? 'Đang lưu…' : 'Lưu điều chỉnh'"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Delete confirmation popup --}}
    <x-ui.confirm-modal show="deleteTarget" cancel="cancelDelete()" confirm="confirmDelete()" id="medicine-delete"
        title="Xóa thuốc?" busy="deleting" confirm-label="Xóa thuốc" busy-label="Đang xóa…">
        <p>
            Thuốc <strong x-text="deleteTarget?.name"></strong> sẽ không còn xuất hiện trong danh mục hoặc gợi ý kê
            toa.
        </p>
        <p class="text-xs text-slate-500">
            Dữ liệu được xóa mềm và không bị xóa vật lý khỏi database.
        </p>
    </x-ui.confirm-modal>
</div>
@endsection
