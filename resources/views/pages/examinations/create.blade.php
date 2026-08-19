@extends('layouts.app')

@section('title', 'Tạo phiếu khám')

@section('content')
<div x-data="examinationCreatePage" x-init="init()" class="mx-auto max-w-3xl space-y-6">
    <x-layout.page-header title="Tạo phiếu khám" description="Chỉ tạo được từ lịch hẹn đang ở trạng thái đã xác nhận.">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('web.examinations.index') }}">
                Quay lại danh sách
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <section class="surface-card p-5 sm:p-6">
        <form x-on:submit.prevent="submit()" novalidate class="space-y-5">
            <div x-cloak x-show="message" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
                role="alert" x-text="message"></div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Lịch hẹn đã xác nhận <span class="text-rose-600">*</span>
                </label>

                <div x-show="preselecting" class="text-sm text-slate-500">Đang tải lịch hẹn…</div>

                <div x-show="!preselecting && form.appointment_id"
                    class="flex items-center justify-between rounded-xl border border-slate-300 px-3 py-2.5">
                    <span class="text-sm text-slate-800" x-text="form.appointment_label"></span>
                    <button type="button" class="text-xs font-semibold text-rose-600" x-on:click="clearAppointment()">
                        Đổi
                    </button>
                </div>

                <div x-show="!preselecting && !form.appointment_id" class="relative">
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

            <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                <x-ui.button type="button" variant="secondary" href="{{ route('web.examinations.index') }}">
                    Hủy
                </x-ui.button>
                <x-ui.button type="submit" x-bind:disabled="submitting">
                    <span x-text="submitting ? 'Đang lưu…' : 'Tạo phiếu khám'"></span>
                </x-ui.button>
            </div>
        </form>
    </section>
</div>
@endsection