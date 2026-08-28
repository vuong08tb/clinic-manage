@extends('layouts.app')

@section('title', 'Toa thuốc')

@section('content')
<div x-data="prescriptionIndexPage" x-on:keydown.escape.window="handleEscape()"
    class="mx-auto max-w-[1400px] space-y-6">
    <x-layout.page-header title="Toa thuốc"
        description="Toa thuốc được kê từ phiếu khám, quản lý thuốc trong toa và tồn kho.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="refresh()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />
                <span x-text="refreshing ? 'Đang tải' : 'Làm mới'"></span>
            </x-ui.button>

            <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">
                <x-ui.icon name="prescription" />
                Tạo toa thuốc
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    {{-- Filters --}}
    <section class="surface-card space-y-4 p-4 sm:p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Bệnh nhân</label>

                <div x-show="filters.patient_id"
                    class="flex items-center justify-between rounded-xl border border-slate-300 px-3 py-2.5">
                    <span class="text-sm text-slate-800" x-text="filters.patient_label"></span>
                    <button type="button" class="text-xs font-semibold text-rose-600" x-on:click="clearPatientFilter()">
                        Bỏ chọn
                    </button>
                </div>

                <div x-show="!filters.patient_id" class="relative">
                    <input type="text" class="form-input" placeholder="Tìm theo tên, SĐT hoặc mã bệnh nhân"
                        x-model="patientQuery" x-on:input.debounce.350ms="searchPatients()">

                    <ul x-show="patientResults.length > 0"
                        class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                        <template x-for="patient in patientResults" :key="patient.id">
                            <li>
                                <button type="button" x-on:click="selectPatientFilter(patient)"
                                    class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">
                                    <span class="font-semibold" x-text="patient.full_name"></span>
                                    <span class="ml-1 text-xs text-slate-500" x-text="`(${patient.code})`"></span>
                                </button>
                            </li>
                        </template>
                    </ul>

                    <p x-show="patientQuery.trim() !== '' && patientResults.length === 0"
                        class="mt-2 text-xs text-slate-400">
                        Không tìm thấy bệnh nhân phù hợp.
                    </p>
                </div>
            </div>

            <div>
                <label for="prescription-doctor" class="mb-2 block text-sm font-semibold text-slate-700">Bác sĩ</label>
                <select id="prescription-doctor" class="form-input" x-model="filters.doctor_id"
                    x-on:change="applyFilters()">
                    <option value="">Tất cả</option>
                    <template x-for="doctor in doctorOptions" :key="doctor.id">
                        <option :value="doctor.id" x-text="doctor.label"></option>
                    </template>
                </select>
            </div>
        </div>
    </section>

    <section x-show="loading" class="surface-card space-y-3 p-6" role="status">
        <template x-for="index in 6" :key="index">
            <div class="h-14 animate-pulse rounded-xl bg-slate-100"></div>
        </template>
    </section>

    <section x-cloak x-show="!loading && listError" class="surface-card p-10 text-center">
        <p class="font-semibold text-rose-700">Không thể tải danh sách toa thuốc</p>
        <p class="mt-2 text-sm text-slate-500" x-text="listError"></p>
        <x-ui.button variant="secondary" class="mt-5" x-on:click="loadList()">Thử lại</x-ui.button>
    </section>

    <section x-cloak x-show="!loading && !listError && prescriptions.length === 0" class="surface-card">
        <x-ui.empty-state icon="prescription" title="Không tìm thấy toa thuốc"
            description="Hãy thử bộ lọc khác hoặc tạo toa thuốc mới từ một phiếu khám.">
            <x-slot:action>
                <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">Tạo toa thuốc</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    </section>

    <section x-cloak x-show="!loading && !listError && prescriptions.length > 0" class="surface-card overflow-hidden">
        {{-- Mobile cards --}}
        <div class="divide-y divide-slate-100 md:hidden">
            <template x-for="prescription in prescriptions" :key="prescription.id">
                <article class="space-y-3 p-4">
                    <p class="text-sm font-semibold text-slate-900" x-text="`Toa #${prescription.id}`"></p>
                    <p class="text-sm text-slate-600" x-text="prescription.examination?.patient?.full_name ?? '—'"></p>
                    <p class="text-xs text-slate-500" x-text="`BS. ${prescription.doctor?.user?.name ?? '—'}`"></p>
                    <p class="text-xs text-slate-500" x-text="`${prescription.items?.length ?? 0} loại thuốc`"></p>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                        <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                            x-on:click="openDetailModal(prescription)" />
                    </div>
                </article>
            </template>
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <th class="px-5 py-3">Mã toa</th>
                        <th class="px-5 py-3">Bệnh nhân</th>
                        <th class="px-5 py-3">Bác sĩ</th>
                        <th class="px-5 py-3">Ngày tạo</th>
                        <th class="px-5 py-3">Số loại thuốc</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="prescription in prescriptions" :key="prescription.id">
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-900"
                                x-text="`#${prescription.id}`"></td>
                            <td class="px-5 py-4 text-sm text-slate-700"
                                x-text="prescription.examination?.patient?.full_name ?? '—'"></td>
                            <td class="px-5 py-4 text-sm text-slate-700"
                                x-text="prescription.doctor?.user?.name ?? '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600"
                                x-text="formatDate(prescription.created_at, { dateStyle: 'medium' })"></td>
                            <td class="px-5 py-4 text-sm text-slate-600"
                                x-text="`${prescription.items?.length ?? 0} loại`"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                                        x-on:click="openDetailModal(prescription)" />
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
                x-text="`Hiển thị ${meta.from ?? 0}–${meta.to ?? 0} trong ${meta.total} toa thuốc`"></p>
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

    {{-- Detail modal: xem toa + quản lý thuốc trong toa (add/sửa/xóa item qua API) --}}
    <x-ui.modal show="detailOpen" close="closeDetailModal()" id="prescription-detail" title="Chi tiết toa thuốc"
        subtitle-expr="detail?.id ? `Mã toa: #${detail.id}` : ''" size="xl">
        <div x-show="detailLoading" class="space-y-3" role="status" aria-label="Đang tải toa thuốc">
            <div class="h-5 w-40 animate-pulse rounded bg-slate-100"></div>
            <div class="h-24 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <p x-cloak x-show="detailError" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            role="alert" x-text="detailError"></p>

        <div x-cloak x-show="!detailLoading && !detailError && detail" class="space-y-6">
            <div>
                <p class="font-bold text-slate-900" x-text="detail?.examination?.patient?.full_name ?? '—'"></p>
                <p class="text-sm text-slate-500" x-text="`BS. ${detail?.doctor?.user?.name ?? '—'}`"></p>
            </div>

            <dl class="grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-slate-500">Phiếu khám liên quan</dt>
                    <dd class="mt-1 font-semibold text-slate-900" x-text="`#${detail?.examination_id}`"></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-slate-500">Chẩn đoán</dt>
                    <dd class="mt-1 text-slate-800" x-text="detail?.examination?.diagnosis ?? '—'"></dd>
                </div>
            </dl>

            <div>
                <h3 class="text-sm font-semibold text-slate-700">Ghi chú</h3>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600" x-text="detail?.notes || 'Không có ghi chú.'">
                </p>
            </div>

            {{-- Danh sách thuốc trong toa (bảng editable qua API item) --}}
            <div class="border-t border-slate-200 pt-5">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Danh sách thuốc</h3>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-2 text-left">Thuốc</th>
                                <th class="px-4 py-2 text-left">SL</th>
                                <th class="px-4 py-2 text-left">Liều dùng</th>
                                <th class="px-4 py-2 text-left">Cách dùng</th>
                                <th class="px-4 py-2 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-if="(detail?.items ?? []).length === 0">
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có thuốc trong toa.
                                    </td>
                                </tr>
                            </template>

                            <template x-for="item in detail?.items ?? []" :key="item.id">
                                <tr>
                                    <template x-if="editingItemId !== item.id">
                                        <td class="px-4 py-2">
                                            <p class="font-semibold text-slate-900" x-text="item.medicine?.name"></p>
                                            <p class="text-xs text-slate-500" x-text="item.medicine?.code"></p>
                                        </td>
                                    </template>
                                    <template x-if="editingItemId !== item.id">
                                        <td class="px-4 py-2" x-text="`${item.quantity} ${item.medicine?.unit ?? ''}`">
                                        </td>
                                    </template>
                                    <template x-if="editingItemId !== item.id">
                                        <td class="px-4 py-2" x-text="item.dosage"></td>
                                    </template>
                                    <template x-if="editingItemId !== item.id">
                                        <td class="px-4 py-2 text-slate-600" x-text="item.usage_instruction || '—'"></td>
                                    </template>
                                    <template x-if="editingItemId !== item.id">
                                        <td class="whitespace-nowrap px-4 py-2 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <x-ui.row-action label="Sửa" icon="edit" tone="neutral"
                                                    x-show="canUpdateItem" x-on:click="startEditItem(item)" />
                                                <x-ui.row-action label="Xóa" icon="trash" tone="danger"
                                                    x-show="canRemoveItem" x-on:click="askDeleteItem(item)" />
                                            </div>
                                        </td>
                                    </template>

                                    {{-- Inline edit row --}}
                                    <template x-if="editingItemId === item.id">
                                        <td colspan="5" class="px-4 py-3">
                                            <div class="grid gap-3 sm:grid-cols-4">
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold text-slate-600">Số
                                                        lượng</label>
                                                    <input type="number" min="1" class="form-input"
                                                        x-model.number="itemEditDraft.quantity"
                                                        x-bind:class="{ 'form-input-error': itemFieldError('quantity') }">
                                                    <p x-cloak x-show="itemFieldError('quantity')"
                                                        class="mt-1 text-xs text-rose-600"
                                                        x-text="itemFieldError('quantity')"></p>
                                                </div>
                                                <div>
                                                    <label class="mb-1 block text-xs font-semibold text-slate-600">Liều
                                                        dùng</label>
                                                    <input type="text" class="form-input"
                                                        x-model.trim="itemEditDraft.dosage"
                                                        x-bind:class="{ 'form-input-error': itemFieldError('dosage') }">
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="mb-1 block text-xs font-semibold text-slate-600">Cách
                                                        dùng</label>
                                                    <input type="text" class="form-input"
                                                        x-model.trim="itemEditDraft.usage_instruction">
                                                </div>
                                            </div>

                                            <div class="mt-3 flex justify-end gap-2">
                                                <x-ui.button variant="secondary" x-on:click="cancelEditItem()"
                                                    x-bind:disabled="itemEditSubmitting">Hủy</x-ui.button>
                                                <x-ui.button x-on:click="saveEditItem(item)"
                                                    x-bind:disabled="itemEditSubmitting">
                                                    <span x-text="itemEditSubmitting ? 'Đang lưu…' : 'Lưu'"></span>
                                                </x-ui.button>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Thêm thuốc mới vào toa đã tồn tại --}}
                <div x-show="canAddItem" class="mt-4 space-y-3 rounded-xl border border-dashed border-slate-300 p-4">
                    <p class="text-sm font-semibold text-slate-700">Thêm thuốc vào toa</p>
                    <x-prescriptions.item-draft-form />
                </div>
            </div>
        </div>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeDetailModal()">Đóng</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Create modal --}}
    <x-ui.modal show="formOpen" close="closeFormModal()" id="prescription-form-modal" title="Tạo toa thuốc"
        subtitle-expr="'Tạo toa thuốc từ một phiếu khám và kê thuốc trước khi lưu.'" size="xl">
        <form id="prescription-form" x-on:submit.prevent="submitForm()" novalidate class="space-y-5">
            <div x-cloak x-show="formMessage"
                class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"
                x-text="formMessage"></div>

            <div>
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

                        <p x-show="examinationPickerPatientQuery.trim() !== '' && examinationPickerPatientResults.length === 0"
                            class="mt-2 text-xs text-slate-400">
                            Không tìm thấy bệnh nhân phù hợp.
                        </p>
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

                    <p x-show="!examinationPickerLoading && examinationPickerPatientQuery.trim() === '' && examinationPickerExaminations.length === 0"
                        class="text-xs text-slate-400">
                        Tìm bệnh nhân để xem danh sách phiếu khám của họ.
                    </p>
                </div>

                <p x-cloak x-show="fieldError('examination_id')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('examination_id')"></p>
            </div>

            <div>
                <label for="prescription-notes" class="mb-2 block text-sm font-semibold text-slate-700">Ghi chú</label>
                <textarea id="prescription-notes" rows="3" class="form-input" x-model.trim="form.notes"
                    x-bind:class="{ 'form-input-error': fieldError('notes') }"></textarea>
                <p x-cloak x-show="fieldError('notes')" class="mt-1.5 text-sm text-rose-600" x-text="fieldError('notes')">
                </p>
            </div>

            {{-- Bảng thuốc nháp — chưa gọi API, chỉ gửi kèm khi submit toa --}}
            <div class="border-t border-slate-200 pt-5">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Danh sách thuốc</h3>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th class="px-4 py-2 text-left">Thuốc</th>
                                <th class="px-4 py-2 text-left">SL</th>
                                <th class="px-4 py-2 text-left">Liều dùng</th>
                                <th class="px-4 py-2 text-left">Cách dùng</th>
                                <th class="px-4 py-2 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-if="form.items.length === 0">
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa thêm thuốc nào.
                                    </td>
                                </tr>
                            </template>

                            <template x-for="item in form.items" :key="item._key">
                                <tr>
                                    <td class="px-4 py-2">
                                        <p class="font-semibold text-slate-900" x-text="item.medicine_label"></p>
                                    </td>
                                    <td class="px-4 py-2" x-text="`${item.quantity} ${item.unit}`"></td>
                                    <td class="px-4 py-2" x-text="item.dosage"></td>
                                    <td class="px-4 py-2 text-slate-600" x-text="item.usage_instruction || '—'"></td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right">
                                        <x-ui.row-action label="Xóa" icon="trash" tone="danger"
                                            x-on:click="removeDraftItem(item)" />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 space-y-3 rounded-xl border border-dashed border-slate-300 p-4">
                    <p class="text-sm font-semibold text-slate-700">Thêm thuốc</p>
                    <x-prescriptions.item-draft-form />
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeFormModal()" x-bind:disabled="submitting">Hủy</x-ui.button>

            <x-ui.button type="submit" form="prescription-form" x-bind:disabled="submitting">
                <span x-show="submitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    aria-hidden="true"></span>
                <span x-text="submitting ? 'Đang lưu…' : 'Tạo toa thuốc'"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Confirm popup: xóa thuốc khỏi toa đã tồn tại --}}
    <x-ui.confirm-modal show="deleteItemTarget" cancel="cancelDeleteItem()" confirm="confirmDeleteItem()"
        id="prescription-item-delete" title="Xóa thuốc khỏi toa?" busy="deletingItem" confirm-label="Xóa"
        busy-label="Đang xóa…">
        <p>
            Thuốc <strong x-text="deleteItemTarget?.medicine?.name"></strong> sẽ được xóa khỏi toa và số lượng
            <strong x-text="deleteItemTarget?.quantity"></strong> sẽ được hoàn lại vào tồn kho.
        </p>
    </x-ui.confirm-modal>
</div>
@endsection
