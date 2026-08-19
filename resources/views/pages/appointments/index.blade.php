@extends('layouts.app')

@section('title', 'Lịch hẹn')

@section('content')
<div x-data="appointmentIndexPage" x-init="init()" x-on:keydown.escape.window="
        statusTarget ? cancelStatusChange() : (createOpen ? closeCreateModal() : (drawerOpen ? closeDrawer() : null))
    " class="mx-auto max-w-[1600px] space-y-6">
    <x-layout.page-header title="Lịch hẹn" description="Quản lý lịch khám của bệnh nhân theo bác sĩ.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="refresh()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />
                <span x-text="refreshing ? 'Đang tải' : 'Làm mới'"></span>
            </x-ui.button>

            <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">
                <x-ui.icon name="calendar" />
                Tạo lịch hẹn
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    {{-- View toggle + filters --}}
    <section class="surface-card space-y-4 p-4 sm:p-5">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" x-on:click="switchView('list')" class="rounded-lg px-3 py-2 text-sm font-semibold"
                x-bind:class="view === 'list' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'">
                Danh sách
            </button>

            <button type="button" x-on:click="switchView('calendar')" class="rounded-lg px-3 py-2 text-sm font-semibold"
                x-bind:class="view === 'calendar' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'">
                Lịch tuần
            </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="appointment-search" class="mb-2 block text-sm font-semibold text-slate-700">
                    Tìm kiếm
                </label>
                <input id="appointment-search" type="search" class="form-input"
                    placeholder="Bệnh nhân, bác sĩ hoặc lý do" maxlength="255" x-model="filters.q"
                    x-on:input.debounce.350ms="search()">
            </div>

            <div x-show="view === 'list'">
                <label for="appointment-date" class="mb-2 block text-sm font-semibold text-slate-700">
                    Ngày
                </label>
                <input id="appointment-date" type="date" class="form-input" x-model="filters.date"
                    x-on:change="applyFilters()">
            </div>

            <div>
                <label for="appointment-status" class="mb-2 block text-sm font-semibold text-slate-700">
                    Trạng thái
                </label>
                <select id="appointment-status" class="form-input" x-model="filters.status"
                    x-on:change="view === 'calendar' ? loadWeek() : applyFilters()">
                    <option value="">Tất cả</option>
                    <option value="scheduled">Đã lên lịch</option>
                    <option value="confirmed">Đã xác nhận</option>
                    <option value="completed">Hoàn thành</option>
                    <option value="cancelled">Đã hủy</option>
                </select>
            </div>

            <div>
                <label for="appointment-doctor" class="mb-2 block text-sm font-semibold text-slate-700">
                    Bác sĩ
                </label>
                <select id="appointment-doctor" class="form-input" x-model="filters.doctor_id"
                    x-on:change="view === 'calendar' ? loadWeek() : applyFilters()">
                    <option value="">Tất cả</option>
                    <template x-for="doctor in doctorOptions" :key="doctor.id">
                        <option :value="doctor.id" x-text="doctor.label"></option>
                    </template>
                </select>
            </div>
        </div>
    </section>

    {{-- LIST VIEW --}}
    <template x-if="view === 'list'">
        <div class="space-y-6">
            <section x-show="loading" class="surface-card space-y-3 p-6" role="status">
                <template x-for="index in 6" :key="index">
                    <div class="h-14 animate-pulse rounded-xl bg-slate-100"></div>
                </template>
            </section>

            <section x-cloak x-show="!loading && listError" class="surface-card p-10 text-center">
                <p class="font-semibold text-rose-700">Không thể tải danh sách lịch hẹn</p>
                <p class="mt-2 text-sm text-slate-500" x-text="listError"></p>
                <x-ui.button variant="secondary" class="mt-5" x-on:click="loadList()">Thử lại</x-ui.button>
            </section>

            <section x-cloak x-show="!loading && !listError && appointments.length === 0" class="surface-card">
                <x-ui.empty-state icon="calendar" title="Không tìm thấy lịch hẹn"
                    description="Hãy thử bộ lọc khác hoặc tạo lịch hẹn mới.">
                    <x-slot:action>
                        <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">Tạo lịch hẹn</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </section>

            <section x-cloak x-show="!loading && !listError && appointments.length > 0"
                class="surface-card overflow-hidden">
                {{-- Mobile cards --}}
                <div class="divide-y divide-slate-100 md:hidden">
                    <template x-for="appointment in appointments" :key="appointment.id">
                        <article class="space-y-2 p-4" x-on:click="openDetail(appointment)">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900"
                                        x-text="`${formatDate(appointment.scheduled_at, { dateStyle: 'medium' })} · ${formatTime(appointment.scheduled_at)}`">
                                    </p>
                                    <p class="mt-1 text-sm text-slate-600"
                                        x-text="appointment.patient?.full_name ?? 'Không rõ bệnh nhân'"></p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    x-bind:class="statusClasses(appointment.status)"
                                    x-text="statusLabel(appointment.status)"></span>
                            </div>
                            <p class="text-xs text-slate-500" x-text="`BS. ${appointment.doctor?.user?.name ?? '—'}`">
                            </p>
                        </article>
                    </template>
                </div>

                {{-- Desktop table --}}
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                <th class="px-5 py-3">Thời gian</th>
                                <th class="px-5 py-3">Bệnh nhân</th>
                                <th class="px-5 py-3">Bác sĩ</th>
                                <th class="px-5 py-3">Trạng thái</th>
                                <th class="px-5 py-3 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-for="appointment in appointments" :key="appointment.id">
                                <tr class="hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-5 py-4 text-sm">
                                        <p class="font-semibold text-slate-900"
                                            x-text="formatDate(appointment.scheduled_at, { dateStyle: 'medium' })"></p>
                                        <p class="text-xs text-slate-500" x-text="formatTime(appointment.scheduled_at)">
                                        </p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-700"
                                        x-text="appointment.patient?.full_name ?? '—'"></td>
                                    <td class="px-5 py-4 text-sm text-slate-700"
                                        x-text="appointment.doctor?.user?.name ?? '—'"></td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                            x-bind:class="statusClasses(appointment.status)"
                                            x-text="statusLabel(appointment.status)"></span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right">
                                        <button x-show="canView" type="button"
                                            class="text-sm font-semibold text-blue-600 hover:text-blue-700"
                                            x-on:click="openDetail(appointment)">
                                            Xem
                                        </button>
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
                        x-text="`Hiển thị ${meta.from ?? 0}–${meta.to ?? 0} trong ${meta.total} lịch hẹn`"></p>
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
                            x-bind:disabled="meta.current_page >= meta.last_page"
                            x-on:click="goToPage(meta.current_page + 1)">
                            Sau
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </template>

    {{-- CALENDAR VIEW --}}
    <template x-if="view === 'calendar'">
        <section class="surface-card space-y-4 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-semibold text-slate-900" x-text="weekRangeLabel"></p>
                <div class="flex items-center gap-2">
                    <x-ui.button variant="secondary" x-on:click="previousWeek()">Tuần trước</x-ui.button>
                    <x-ui.button variant="secondary" x-on:click="goToCurrentWeek()">Hôm nay</x-ui.button>
                    <x-ui.button variant="secondary" x-on:click="nextWeek()">Tuần sau</x-ui.button>
                </div>
            </div>

            <div x-show="weekLoading" class="grid grid-cols-1 gap-3 sm:grid-cols-7">
                <template x-for="index in 7" :key="index">
                    <div class="h-40 animate-pulse rounded-xl bg-slate-100"></div>
                </template>
            </div>

            <p x-cloak x-show="!weekLoading && weekError" class="text-sm text-rose-600" x-text="weekError"></p>

            <div x-cloak x-show="!weekLoading && !weekError" class="grid grid-cols-1 gap-3 sm:grid-cols-7">
                <template x-for="day in weekDays" :key="day.dateStr">
                    <div class="rounded-xl border border-slate-200 p-3">
                        <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase" x-text="day.label"></p>
                        <p class="mb-3 text-sm font-bold text-slate-900"
                            x-text="formatDate(day.date, { day: '2-digit', month: '2-digit' })"></p>

                        <div class="space-y-2">
                            <template x-for="appointment in day.appointments" :key="appointment.id">
                                <button type="button" x-on:click="openDetail(appointment)"
                                    class="block w-full rounded-lg p-2 text-left text-xs ring-1 ring-inset"
                                    x-bind:class="statusClasses(appointment.status)">
                                    <p class="font-semibold" x-text="formatTime(appointment.scheduled_at)"></p>
                                    <p class="truncate" x-text="appointment.patient?.full_name ?? '—'"></p>
                                </button>
                            </template>

                            <p x-show="day.appointments.length === 0" class="text-xs text-slate-400">
                                Không có lịch
                            </p>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </template>

    {{-- CREATE MODAL --}}
    <div x-cloak x-show="createOpen" class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog"
        aria-modal="true" aria-labelledby="create-appointment-title">
        <div x-show="createOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/40"
            x-on:click="closeCreateModal()"></div>

        <div x-show="createOpen" x-transition class="surface-card relative w-full max-w-lg p-6">
            <div class="mb-5 flex items-center justify-between">
                <h2 id="create-appointment-title" class="text-lg font-bold text-slate-900">Tạo lịch hẹn</h2>
                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                    x-on:click="closeCreateModal()" aria-label="Đóng form">
                    <x-ui.icon name="close" />
                </button>
            </div>

            <form x-on:submit.prevent="submitCreate()" novalidate class="space-y-4">
                <div x-cloak x-show="createMessage"
                    class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert"
                    x-text="createMessage"></div>

                <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        Bệnh nhân <span class="text-rose-600">*</span>
                    </label>

                    <div x-show="createForm.patient_id"
                        class="flex items-center justify-between rounded-xl border border-slate-300 px-3 py-2.5">
                        <span class="text-sm text-slate-800" x-text="createForm.patient_label"></span>
                        <button type="button" class="text-xs font-semibold text-rose-600"
                            x-on:click="clearSelectedPatient()">
                            Đổi
                        </button>
                    </div>

                    <div x-show="!createForm.patient_id" class="relative">
                        <input type="text" class="form-input" placeholder="Tìm theo tên, SĐT hoặc mã bệnh nhân"
                            x-model="patientQuery" x-on:input.debounce.350ms="searchPatients()"
                            x-bind:class="{ 'form-input-error': createFieldError('patient_id') }">

                        <ul x-show="patientResults.length > 0"
                            class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                            <template x-for="patient in patientResults" :key="patient.id">
                                <li>
                                    <button type="button" x-on:click="selectPatient(patient)"
                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">
                                        <span class="font-semibold" x-text="patient.full_name"></span>
                                        <span class="ml-1 text-xs text-slate-500" x-text="`(${patient.code})`"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <p x-cloak x-show="createFieldError('patient_id')" class="mt-1.5 text-sm text-rose-600"
                        x-text="createFieldError('patient_id')"></p>
                </div>

                <div>
                    <label for="appointment-doctor-select" class="mb-2 block text-sm font-semibold text-slate-700">
                        Bác sĩ <span class="text-rose-600">*</span>
                    </label>
                    <select id="appointment-doctor-select" class="form-input" required x-model="createForm.doctor_id"
                        x-bind:class="{ 'form-input-error': createFieldError('doctor_id') }">
                        <option value="">Chọn bác sĩ</option>
                        <template x-for="doctor in doctorOptions" :key="doctor.id">
                            <option :value="doctor.id" x-text="doctor.label"></option>
                        </template>
                    </select>
                    <p x-cloak x-show="createFieldError('doctor_id')" class="mt-1.5 text-sm text-rose-600"
                        x-text="createFieldError('doctor_id')"></p>
                </div>

                <div>
                    <label for="appointment-scheduled-at" class="mb-2 block text-sm font-semibold text-slate-700">
                        Thời gian hẹn <span class="text-rose-600">*</span>
                    </label>
                    <input id="appointment-scheduled-at" type="datetime-local" class="form-input" required
                        x-model="createForm.scheduled_at"
                        x-bind:class="{ 'form-input-error': createFieldError('scheduled_at') }">
                    <p x-cloak x-show="createFieldError('scheduled_at')" class="mt-1.5 text-sm text-rose-600"
                        x-text="createFieldError('scheduled_at')"></p>
                </div>

                <div>
                    <label for="appointment-reason" class="mb-2 block text-sm font-semibold text-slate-700">
                        Lý do khám
                    </label>
                    <textarea id="appointment-reason" rows="2" class="form-input" maxlength="255"
                        x-model.trim="createForm.reason"
                        x-bind:class="{ 'form-input-error': createFieldError('reason') }"></textarea>
                    <p x-cloak x-show="createFieldError('reason')" class="mt-1.5 text-sm text-rose-600"
                        x-text="createFieldError('reason')"></p>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-4">
                    <x-ui.button type="button" variant="secondary" x-on:click="closeCreateModal()"
                        x-bind:disabled="creating">
                        Hủy
                    </x-ui.button>
                    <x-ui.button type="submit" x-bind:disabled="creating">
                        <span x-text="creating ? 'Đang lưu…' : 'Tạo lịch hẹn'"></span>
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>

    {{-- DETAIL / EDIT DRAWER --}}
    <div x-cloak x-show="drawerOpen" class="fixed inset-0 z-50" role="dialog" aria-modal="true"
        aria-labelledby="appointment-drawer-title">
        <div x-show="drawerOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/40"
            x-on:click="closeDrawer()"></div>

        <aside x-show="drawerOpen" x-transition:enter="transition duration-200"
            x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
            x-transition:leave="transition duration-150" x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 id="appointment-drawer-title" class="text-lg font-bold text-slate-900">Chi tiết lịch hẹn</h2>
                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                    x-on:click="closeDrawer()" aria-label="Đóng">
                    <x-ui.icon name="close" />
                </button>
            </div>

            <div class="flex-1 space-y-6 overflow-y-auto p-5" x-show="selected">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-bold text-slate-900" x-text="selected?.patient?.full_name"></p>
                        <p class="text-sm text-slate-500" x-text="`BS. ${selected?.doctor?.user?.name ?? '—'}`"></p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                        x-bind:class="statusClasses(selected?.status)" x-text="statusLabel(selected?.status)"></span>
                </div>

                {{-- Status transition actions --}}
                <div x-show="canUpdateStatus && allowedNextStatuses(selected?.status).length > 0"
                    class="flex flex-wrap gap-2">
                    <template x-for="next in allowedNextStatuses(selected?.status)" :key="next">
                        <button type="button" x-on:click="askStatusChange(next)"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            x-text="`Chuyển sang: ${statusLabel(next)}`"></button>
                    </template>
                </div>

                {{-- Inline confirm --}}
                <div x-cloak x-show="statusTarget" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm text-amber-800"
                        x-text="`Xác nhận chuyển trạng thái sang \u201c${statusLabel(statusTarget)}\u201d?`"></p>
                    <div class="mt-3 flex justify-end gap-2">
                        <x-ui.button variant="secondary" x-on:click="cancelStatusChange()"
                            x-bind:disabled="updatingStatus">
                            Hủy
                        </x-ui.button>
                        <x-ui.button x-on:click="confirmStatusChange()" x-bind:disabled="updatingStatus">
                            <span x-text="updatingStatus ? 'Đang cập nhật…' : 'Xác nhận'"></span>
                        </x-ui.button>
                    </div>
                </div>

                {{-- Edit form: only when scheduled + có quyền UPDATE --}}
                <form x-show="editForm" x-on:submit.prevent="submitEdit()" novalidate
                    class="space-y-4 border-t border-slate-200 pt-5">
                    <div x-cloak x-show="editMessage"
                        class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"
                        x-text="editMessage"></div>

                    <div>
                        <label for="edit-scheduled-at" class="mb-2 block text-sm font-semibold text-slate-700">
                            Thời gian hẹn
                        </label>
                        <input id="edit-scheduled-at" type="datetime-local" class="form-input" required
                            x-model="editForm.scheduled_at"
                            x-bind:class="{ 'form-input-error': editFieldError('scheduled_at') }">
                        <p x-cloak x-show="editFieldError('scheduled_at')" class="mt-1.5 text-sm text-rose-600"
                            x-text="editFieldError('scheduled_at')"></p>
                    </div>

                    <div>
                        <label for="edit-reason" class="mb-2 block text-sm font-semibold text-slate-700">
                            Lý do khám
                        </label>
                        <textarea id="edit-reason" rows="2" class="form-input" maxlength="255"
                            x-model.trim="editForm.reason"
                            x-bind:class="{ 'form-input-error': editFieldError('reason') }"></textarea>
                        <p x-cloak x-show="editFieldError('reason')" class="mt-1.5 text-sm text-rose-600"
                            x-text="editFieldError('reason')"></p>
                    </div>

                    <div class="flex justify-end">
                        <x-ui.button type="submit" x-bind:disabled="editing">
                            <span x-text="editing ? 'Đang lưu…' : 'Lưu thay đổi'"></span>
                        </x-ui.button>
                    </div>
                </form>

                <p x-show="!editForm" class="text-xs text-slate-400">
                    Chỉ lịch hẹn ở trạng thái "Đã lên lịch" mới có thể sửa thời gian/lý do.
                </p>
            </div>
        </aside>
    </div>
</div>
@endsection