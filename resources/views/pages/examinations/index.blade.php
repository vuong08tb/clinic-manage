@extends('layouts.app')

@section('title', 'Phiếu khám')

@section('content')
<div x-data="examinationIndexPage" x-on:keydown.escape.window="handleEscape()"
    class="mx-auto max-w-[1400px] space-y-6">
    <x-layout.page-header title="Phiếu khám" description="Hồ sơ khám bệnh được tạo từ lịch hẹn đã xác nhận.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="refresh()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />
                <span x-text="refreshing ? 'Đang tải' : 'Làm mới'"></span>
            </x-ui.button>

            <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">
                <x-ui.icon name="examination" />
                Tạo phiếu khám
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
                <label for="examination-doctor" class="mb-2 block text-sm font-semibold text-slate-700">Bác sĩ</label>
                <select id="examination-doctor" class="form-input" x-model="filters.doctor_id"
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
        <p class="font-semibold text-rose-700">Không thể tải danh sách phiếu khám</p>
        <p class="mt-2 text-sm text-slate-500" x-text="listError"></p>
        <x-ui.button variant="secondary" class="mt-5" x-on:click="loadList()">Thử lại</x-ui.button>
    </section>

    <section x-cloak x-show="!loading && !listError && examinations.length === 0" class="surface-card">
        <x-ui.empty-state icon="examination" title="Không tìm thấy phiếu khám"
            description="Hãy thử bộ lọc khác hoặc tạo phiếu khám mới từ lịch hẹn đã xác nhận.">
            <x-slot:action>
                <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">Tạo phiếu khám</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    </section>

    <section x-cloak x-show="!loading && !listError && examinations.length > 0" class="surface-card overflow-hidden">
        {{-- Mobile cards --}}
        <div class="divide-y divide-slate-100 md:hidden">
            <template x-for="examination in examinations" :key="examination.id">
                <article class="space-y-3 p-4">
                    <p class="text-sm font-semibold text-slate-900"
                        x-text="`${formatDate(examination.examined_at, { dateStyle: 'medium' })} · ${formatTime(examination.examined_at)}`">
                    </p>
                    <p class="text-sm text-slate-600" x-text="examination.patient?.full_name ?? '—'"></p>
                    <p class="text-xs text-slate-500" x-text="`BS. ${examination.doctor?.user?.name ?? '—'}`"></p>
                    <p class="truncate text-xs text-slate-500" x-text="examination.diagnosis"></p>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                        <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                            x-on:click="openDetailModal(examination)" />

                        <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                            x-on:click="openEditModal(examination)" />
                    </div>
                </article>
            </template>
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <th class="px-5 py-3">Ngày khám</th>
                        <th class="px-5 py-3">Bệnh nhân</th>
                        <th class="px-5 py-3">Bác sĩ</th>
                        <th class="px-5 py-3">Chẩn đoán</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="examination in examinations" :key="examination.id">
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-4 text-sm">
                                <p class="font-semibold text-slate-900"
                                    x-text="formatDate(examination.examined_at, { dateStyle: 'medium' })"></p>
                                <p class="text-xs text-slate-500" x-text="formatTime(examination.examined_at)"></p>
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-700" x-text="examination.patient?.full_name ?? '—'">
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-700" x-text="examination.doctor?.user?.name ?? '—'">
                            </td>
                            <td class="px-5 py-4 max-w-xs truncate text-sm text-slate-600"
                                x-text="examination.diagnosis"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                                        x-on:click="openDetailModal(examination)" />

                                    <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                                        x-on:click="openEditModal(examination)" />
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
                x-text="`Hiển thị ${meta.from ?? 0}–${meta.to ?? 0} trong ${meta.total} phiếu khám`"></p>
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
    <x-ui.modal show="detailOpen" close="closeDetailModal()" id="examination-detail" title="Chi tiết phiếu khám"
        subtitle-expr="detail?.id ? `Mã phiếu khám: #${detail.id}` : ''">
        <div x-show="detailLoading" class="space-y-3" role="status" aria-label="Đang tải phiếu khám">
            <div class="h-5 w-40 animate-pulse rounded bg-slate-100"></div>
            <div class="h-24 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <p x-cloak x-show="detailError" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            role="alert" x-text="detailError"></p>

        <div x-cloak x-show="!detailLoading && !detailError && detail" class="space-y-5">
            <div>
                <p class="font-bold text-slate-900" x-text="detail?.patient?.full_name ?? '—'"></p>
                <p class="text-sm text-slate-500" x-text="`BS. ${detail?.doctor?.user?.name ?? '—'}`"></p>
            </div>

            <dl class="grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-slate-500">Ngày khám</dt>
                    <dd class="mt-1 font-semibold text-slate-900" x-text="formatDate(detail?.examined_at)"></dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Mã bệnh nhân</dt>
                    <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.patient?.code ?? '—'"></dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Lịch hẹn liên quan</dt>
                    <dd class="mt-1 font-semibold text-slate-900" x-text="`#${detail?.appointment_id}`"></dd>
                </div>
            </dl>

            <div class="border-t border-slate-200 pt-5">
                <h3 class="text-sm font-semibold text-slate-700">Chẩn đoán</h3>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-800" x-text="detail?.diagnosis"></p>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-slate-700">Ghi chú</h3>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600"
                    x-text="detail?.notes || 'Không có ghi chú.'"></p>
            </div>
        </div>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeDetailModal()">
                Đóng
            </x-ui.button>

            <x-ui.button x-show="canUpdate" x-on:click="editFromDetail()">
                Sửa phiếu khám
            </x-ui.button>

            <x-ui.button x-show="canCreatePrescription" x-on:click="goToCreatePrescription()">
                Tạo toa thuốc
            </x-ui.button>

            <x-ui.button x-show="canCreateInvoice" x-on:click="goToCreateInvoice()">
                Tạo hóa đơn
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Create/update modal --}}
    <x-ui.modal show="formOpen" close="closeFormModal()" id="examination-form-modal"
        title-expr="formMode === 'create' ? 'Tạo phiếu khám' : 'Cập nhật phiếu khám'"
        subtitle-expr="formMode === 'create' ? 'Chỉ tạo được từ lịch hẹn đang ở trạng thái đã xác nhận.' : 'Cập nhật chẩn đoán và ghi chú của phiếu khám.'">
        <form id="examination-form" x-on:submit.prevent="submitForm()" novalidate class="space-y-5">
            <div x-cloak x-show="formMessage"
                class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"
                x-text="formMessage"></div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Lịch hẹn đã xác nhận <span class="text-rose-600">*</span>
                </label>

                <div x-show="preselecting" class="text-sm text-slate-500">Đang tải lịch hẹn…</div>

                <div x-show="!preselecting && form.appointment_id"
                    class="flex items-center justify-between rounded-xl border border-slate-300 px-3 py-2.5">
                    <span class="text-sm text-slate-800" x-text="form.appointment_label"></span>
                    <button type="button" x-show="formMode === 'create'" class="text-xs font-semibold text-rose-600"
                        x-on:click="clearAppointment()">
                        Đổi
                    </button>
                </div>

                <div x-show="!preselecting && !form.appointment_id && formMode === 'create'" class="relative">
                    <input type="text" class="form-input" placeholder="Tìm theo tên bệnh nhân, bác sĩ hoặc lý do khám"
                        x-model="appointmentQuery" x-on:input.debounce.350ms="searchAppointments()"
                        x-bind:class="{ 'form-input-error': fieldError('appointment_id') }">

                    <ul x-show="appointmentResults.length > 0"
                        class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                        <template x-for="appointment in appointmentResults" :key="appointment.id">
                            <li>
                                <button type="button" x-on:click="selectAppointment(appointment)"
                                    class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">
                                    <span class="font-semibold" x-text="appointment.patient?.full_name ?? '—'"></span>
                                    <span class="ml-1 text-xs text-slate-500"
                                        x-text="`· BS. ${appointment.doctor?.user?.name ?? '—'}`"></span>
                                </button>
                            </li>
                        </template>
                    </ul>

                    <p x-show="!searchingAppointment && appointmentQuery.trim() !== '' && appointmentResults.length === 0"
                        class="mt-2 text-xs text-slate-400">
                        Không tìm thấy lịch hẹn đã xác nhận phù hợp.
                    </p>
                </div>

                <p x-cloak x-show="fieldError('appointment_id')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('appointment_id')"></p>
            </div>

            <div>
                <label for="examination-diagnosis" class="mb-2 block text-sm font-semibold text-slate-700">
                    Chẩn đoán <span class="text-rose-600">*</span>
                </label>
                <textarea id="examination-diagnosis" rows="4" class="form-input" required x-model.trim="form.diagnosis"
                    x-bind:class="{ 'form-input-error': fieldError('diagnosis') }"></textarea>
                <p x-cloak x-show="fieldError('diagnosis')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('diagnosis')"></p>
            </div>

            <div>
                <label for="examination-notes" class="mb-2 block text-sm font-semibold text-slate-700">
                    Ghi chú
                </label>
                <textarea id="examination-notes" rows="3" class="form-input" x-model.trim="form.notes"
                    x-bind:class="{ 'form-input-error': fieldError('notes') }"></textarea>
                <p x-cloak x-show="fieldError('notes')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('notes')"></p>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeFormModal()" x-bind:disabled="submitting">
                Hủy
            </x-ui.button>

            <x-ui.button type="submit" form="examination-form" x-bind:disabled="submitting">
                <span x-show="submitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    aria-hidden="true"></span>

                <span x-text="submitting
                        ? 'Đang lưu…'
                        : (formMode === 'create' ? 'Tạo phiếu khám' : 'Lưu thay đổi')"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
@endsection
