@extends('layouts.app')

@section('title', 'Chi tiết bệnh nhân')

@section('content')
<div x-data="patientShowPage({{ (int) request()->route('patient') }})" x-init="init()"
    class="mx-auto max-w-5xl space-y-6">
    <x-layout.page-header title="Chi tiết bệnh nhân" description="Thông tin hồ sơ và liên hệ của bệnh nhân.">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('web.patients.index') }}">
                Quay lại danh sách
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <section x-show="loading" class="surface-card space-y-4 p-6" role="status" aria-label="Đang tải hồ sơ bệnh nhân">
        <div class="h-5 w-28 animate-pulse rounded bg-slate-100"></div>

        <div class="h-9 w-64 animate-pulse rounded bg-slate-100"></div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>

            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>

            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>

            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
        </div>
    </section>

    <section x-cloak x-show="!loading && error" class="surface-card p-10 text-center">
        <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-rose-50 text-rose-600">
            <x-ui.icon name="patients" />
        </span>

        <p class="mt-4 font-semibold text-rose-700" x-text="error"></p>

        <div class="mt-5 flex justify-center gap-3">
            <x-ui.button variant="secondary" x-on:click="loadPatient()">
                Thử lại
            </x-ui.button>

            <x-ui.button href="{{ route('web.patients.index') }}">
                Về danh sách
            </x-ui.button>
        </div>
    </section>

    <section x-cloak x-show="!loading && !error && patient" class="surface-card overflow-hidden">
        <div class="border-b border-slate-200 bg-gradient-to-r from-blue-700 to-blue-600 p-6 text-white">
            <p class="text-sm font-semibold text-blue-100" x-text="patient?.code"></p>

            <h2 class="mt-2 text-2xl font-bold" x-text="patient?.full_name"></h2>

            <p class="mt-2 text-sm text-blue-100">
                Hồ sơ thông tin bệnh nhân
            </p>
        </div>

        <dl class="grid gap-6 p-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-slate-500">
                    Giới tính
                </dt>

                <dd class="mt-1 font-semibold text-slate-900" x-text="genderLabel(patient?.gender)"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">
                    Ngày sinh
                </dt>

                <dd class="mt-1 font-semibold text-slate-900" x-text="
                            formatDateOnly(patient?.date_of_birth)
                        "></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">
                    Điện thoại
                </dt>

                <dd class="mt-1 font-semibold text-slate-900" x-text="patient?.phone"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">
                    Email
                </dt>

                <dd class="mt-1 font-semibold text-slate-900" x-text="
                            patient?.email || 'Chưa có email'
                        "></dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-slate-500">
                    Địa chỉ
                </dt>

                <dd class="mt-1 font-semibold text-slate-900" x-text="
                            patient?.address || 'Chưa có địa chỉ'
                        "></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">
                    Ngày tạo hồ sơ
                </dt>

                <dd class="mt-1 font-semibold text-slate-900" x-text="formatDate(patient?.created_at)"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">
                    Cập nhật gần nhất
                </dt>

                <dd class="mt-1 font-semibold text-slate-900" x-text="formatDate(patient?.updated_at)"></dd>
            </div>
        </dl>
    </section>
</div>
@endsection