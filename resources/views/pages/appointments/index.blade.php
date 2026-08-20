@extends('layouts.app')

@section('title', 'Lịch hẹn')

@section('content')
<div x-data="appointmentIndexPage" x-on:keydown.escape.window="handleEscape()"
    class="mx-auto max-w-[1600px] space-y-6">
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
                        <article class="space-y-3 p-4">
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

                            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                                <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                                    x-on:click="openDetailModal(appointment)" />

                                <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canEdit(appointment)"
                                    x-on:click="openEditModal(appointment)" />

                                <template x-for="next in statusActions(appointment)" :key="next">
                                    <x-ui.row-action label-expr="`Chuyển sang: ${statusLabel(next)}`"
                                        tone-expr="next === 'cancelled' ? 'danger' : 'success'"
                                        x-on:click="askStatusChange(appointment, next)">
                                        <x-ui.icon name="check" size="h-4 w-4" x-show="next !== 'cancelled'" />
                                        <x-ui.icon name="ban" size="h-4 w-4" x-show="next === 'cancelled'" />
                                    </x-ui.row-action>
                                </template>
                            </div>
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
                                    <td class="px-5 py-4 text-right">
                                        <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                            <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                                                x-on:click="openDetailModal(appointment)" />

                                            <x-ui.row-action label="Sửa" icon="edit" tone="neutral"
                                                x-show="canEdit(appointment)" x-on:click="openEditModal(appointment)" />

                                            {{-- Status transitions live in the row so they do not need the detail modal --}}
                                            <template x-for="next in statusActions(appointment)" :key="next">
                                                <x-ui.row-action label-expr="`Chuyển sang: ${statusLabel(next)}`"
                                                    tone-expr="next === 'cancelled' ? 'danger' : 'success'"
                                                    x-on:click="askStatusChange(appointment, next)">
                                                    <x-ui.icon name="check" size="h-4 w-4"
                                                        x-show="next !== 'cancelled'" />
                                                    <x-ui.icon name="ban" size="h-4 w-4"
                                                        x-show="next === 'cancelled'" />
                                                </x-ui.row-action>
                                            </template>
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
                                <button type="button" x-on:click="openDetailModal(appointment)"
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

    {{-- Detail modal --}}
    <x-ui.modal show="detailOpen" close="closeDetailModal()" id="appointment-detail" title="Chi tiết lịch hẹn"
        subtitle-expr="detail?.id ? `Mã lịch hẹn: #${detail.id}` : ''">
        <div x-show="detailLoading" class="space-y-3" role="status" aria-label="Đang tải lịch hẹn">
            <div class="h-5 w-40 animate-pulse rounded bg-slate-100"></div>
            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <p x-cloak x-show="detailError" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            role="alert" x-text="detailError"></p>

        <div x-cloak x-show="!detailLoading && !detailError && detail" class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-bold text-slate-900" x-text="detail?.patient?.full_name ?? '—'"></p>
                    <p class="text-sm text-slate-500" x-text="`BS. ${detail?.doctor?.user?.name ?? '—'}`"></p>
                </div>

                <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                    x-bind:class="statusClasses(detail?.status)" x-text="statusLabel(detail?.status)"></span>
            </div>

            <dl class="grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-slate-500">Ngày hẹn</dt>
                    <dd class="mt-1 font-semibold text-slate-900" x-text="formatDate(detail?.scheduled_at)"></dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Giờ hẹn</dt>
                    <dd class="mt-1 font-semibold text-slate-900" x-text="formatTime(detail?.scheduled_at)"></dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Mã bệnh nhân</dt>
                    <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.patient?.code ?? '—'"></dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-slate-500">Điện thoại</dt>
                    <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.patient?.phone ?? '—'"></dd>
                </div>

                <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-slate-500">Lý do khám</dt>
                    <dd class="mt-1 whitespace-pre-line font-semibold text-slate-900"
                        x-text="detail?.reason || 'Không có ghi chú.'"></dd>
                </div>
            </dl>

            <a x-show="detail?.status === 'confirmed' && canCreateExamination"
                x-bind:href="examinationCreateUrl(detail)"
                class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                <x-ui.icon name="examination" size="h-4 w-4" />
                Tạo phiếu khám
            </a>

            <p x-show="!canEdit(detail)" class="text-xs text-slate-400">
                Chỉ lịch hẹn ở trạng thái "Đã lên lịch" mới có thể sửa thời gian/lý do.
                Chuyển trạng thái được thực hiện ở cột "Thao tác" của danh sách.
            </p>
        </div>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeDetailModal()">
                Đóng
            </x-ui.button>

            <x-ui.button x-show="canEdit(detail)" x-on:click="editFromDetail()">
                Sửa lịch hẹn
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Create/update modal --}}
    <x-ui.modal show="formOpen" close="closeFormModal()" id="appointment-form-modal"
        title-expr="formMode === 'create' ? 'Tạo lịch hẹn' : 'Cập nhật lịch hẹn'"
        subtitle-expr="formMode === 'edit' ? 'Chỉ sửa được thời gian và lý do khám.' : 'Chọn bệnh nhân, bác sĩ và thời gian khám.'">
        <form id="appointment-form" x-on:submit.prevent="submitForm()" novalidate class="space-y-4">
            <div x-cloak x-show="formMessage"
                class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700" role="alert"
                x-text="formMessage"></div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Bệnh nhân <span class="text-rose-600">*</span>
                </label>

                <div x-show="form.patient_id"
                    class="flex items-center justify-between rounded-xl border border-slate-300 px-3 py-2.5">
                    <span class="text-sm text-slate-800" x-text="form.patient_label"></span>
                    <button type="button" x-show="formMode === 'create'" class="text-xs font-semibold text-rose-600"
                        x-on:click="clearSelectedPatient()">
                        Đổi
                    </button>
                </div>

                <div x-show="!form.patient_id" class="relative">
                    <input type="text" class="form-input" placeholder="Tìm theo tên, SĐT hoặc mã bệnh nhân"
                        x-model="patientQuery" x-on:input.debounce.350ms="searchPatients()"
                        x-bind:class="{ 'form-input-error': fieldError('patient_id') }">

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

                <p x-cloak x-show="fieldError('patient_id')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('patient_id')"></p>
            </div>

            <div>
                <label for="appointment-doctor-select" class="mb-2 block text-sm font-semibold text-slate-700">
                    Bác sĩ <span class="text-rose-600">*</span>
                </label>
                <select id="appointment-doctor-select" class="form-input" required x-model="form.doctor_id"
                    x-bind:disabled="formMode === 'edit'"
                    x-bind:class="{ 'form-input-error': fieldError('doctor_id') }">
                    <option value="">Chọn bác sĩ</option>
                    <template x-for="doctor in doctorOptions" :key="doctor.id">
                        <option :value="doctor.id" x-text="doctor.label"></option>
                    </template>
                </select>
                <p x-cloak x-show="fieldError('doctor_id')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('doctor_id')"></p>
            </div>

            <div>
                <label for="appointment-scheduled-at" class="mb-2 block text-sm font-semibold text-slate-700">
                    Thời gian hẹn <span class="text-rose-600">*</span>
                </label>
                <input id="appointment-scheduled-at" type="datetime-local" class="form-input" required
                    x-model="form.scheduled_at" x-bind:class="{ 'form-input-error': fieldError('scheduled_at') }">
                <p x-cloak x-show="fieldError('scheduled_at')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('scheduled_at')"></p>
            </div>

            <div>
                <label for="appointment-reason" class="mb-2 block text-sm font-semibold text-slate-700">
                    Lý do khám
                </label>
                <textarea id="appointment-reason" rows="2" class="form-input" maxlength="255" x-model.trim="form.reason"
                    x-bind:class="{ 'form-input-error': fieldError('reason') }"></textarea>
                <p x-cloak x-show="fieldError('reason')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('reason')"></p>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeFormModal()" x-bind:disabled="submitting">
                Hủy
            </x-ui.button>

            <x-ui.button type="submit" form="appointment-form" x-bind:disabled="submitting">
                <span x-show="submitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    aria-hidden="true"></span>

                <span x-text="submitting
                        ? 'Đang lưu…'
                        : (formMode === 'create' ? 'Tạo lịch hẹn' : 'Lưu thay đổi')"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Status change confirmation popup --}}
    <x-ui.confirm-modal show="statusTarget" cancel="cancelStatusChange()" confirm="confirmStatusChange()"
        id="appointment-status" title="Chuyển trạng thái lịch hẹn?" busy="updatingStatus" variant="primary"
        confirm-label="Xác nhận" busy-label="Đang cập nhật…">
        <p>
            Lịch hẹn của
            <strong x-text="statusTarget?.appointment?.patient?.full_name ?? 'bệnh nhân'"></strong>
            sẽ chuyển sang trạng thái
            <strong x-text="statusLabel(statusTarget?.status)"></strong>.
        </p>

        <p class="text-xs text-slate-500">
            Backend là nơi kiểm tra cuối cùng các bước chuyển trạng thái được phép.
        </p>
    </x-ui.confirm-modal>
</div>
@endsection