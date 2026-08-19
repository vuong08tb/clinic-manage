@extends('layouts.app')

@section('title', 'Phiếu khám')

@section('content')
<div x-data="examinationIndexPage" x-init="init()" class="mx-auto max-w-[1400px] space-y-6">
    <x-layout.page-header title="Phiếu khám" description="Hồ sơ khám bệnh được tạo từ lịch hẹn đã xác nhận.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="refresh()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />
                <span x-text="refreshing ? 'Đang tải' : 'Làm mới'"></span>
            </x-ui.button>

            <x-ui.button x-show="canCreate" href="{{ route('web.examinations.create') }}">
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
                <x-ui.button x-show="canCreate" href="{{ route('web.examinations.create') }}">Tạo phiếu khám
                </x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    </section>

    <section x-cloak x-show="!loading && !listError && examinations.length > 0" class="surface-card overflow-hidden">
        {{-- Mobile cards --}}
        <div class="divide-y divide-slate-100 md:hidden">
            <template x-for="examination in examinations" :key="examination.id">
                <a x-bind:href="examinationDetailUrl(examination)" class="block space-y-2 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-900"
                            x-text="`${formatDate(examination.examined_at, { dateStyle: 'medium' })} · ${formatTime(examination.examined_at)}`">
                        </p>
                    </div>
                    <p class="text-sm text-slate-600" x-text="examination.patient?.full_name ?? '—'"></p>
                    <p class="text-xs text-slate-500" x-text="`BS. ${examination.doctor?.user?.name ?? '—'}`"></p>
                    <p class="truncate text-xs text-slate-500" x-text="examination.diagnosis"></p>
                </a>
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
                                <a x-show="canView" x-bind:href="examinationDetailUrl(examination)"
                                    class="text-sm font-semibold text-blue-600 hover:text-blue-700">
                                    Xem
                                </a>
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
</div>
@endsection