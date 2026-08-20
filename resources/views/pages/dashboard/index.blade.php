@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div x-data="dashboardPage" class="mx-auto max-w-[1600px] space-y-7">
    <x-layout.page-header title="Dashboard" description="Tổng quan công việc theo quyền truy cập của bạn.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="load()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />
                <span x-text="refreshing ? 'Đang cập nhật' : 'Làm mới'"></span>
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <section class="surface-card overflow-hidden bg-gradient-to-r from-blue-700 to-blue-600 p-6 text-white sm:p-7">
        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-blue-100" x-text="todayLabel"></p>
                <h2 class="mt-2 text-2xl font-bold">
                    Xin chào, <span x-text="$store.auth.user?.name"></span>
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-100">
                    Các số liệu dưới đây chỉ được tải từ những module bạn có quyền truy cập.
                </p>
            </div>
            <div class="shrink-0 rounded-2xl border border-white/20 bg-white/10 px-5 py-3 backdrop-blur">
                <p class="text-xs font-semibold tracking-wider text-blue-100 uppercase">Vai trò</p>
                <p class="mt-1 font-bold" x-text="$store.auth.roleName"></p>
            </div>
        </div>
    </section>

    <section aria-labelledby="overview-heading">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 id="overview-heading" class="text-base font-bold text-slate-900">Tổng quan</h2>
                <p class="mt-1 text-sm text-slate-500">Dữ liệu cập nhật trực tiếp từ hệ thống.</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <template x-for="widget in widgets" :key="widget.key">
                <article class="surface-card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-medium text-slate-500" x-text="widget.label"></p>
                            <div x-show="widget.loading" class="mt-3 h-9 w-20 animate-pulse rounded-lg bg-slate-100">
                            </div>
                            <p x-show="!widget.loading" class="mt-2 text-3xl font-bold tracking-tight text-slate-950"
                                x-text="widgetValue(widget)"></p>
                        </div>
                        <span class="grid h-11 w-11 place-items-center rounded-xl text-sm font-bold" :class="{
                                    'bg-blue-50 text-blue-700': widget.tone === 'blue',
                                    'bg-teal-50 text-teal-700': widget.tone === 'teal',
                                    'bg-violet-50 text-violet-700': widget.tone === 'violet',
                                    'bg-amber-50 text-amber-700': widget.tone === 'amber',
                                    'bg-rose-50 text-rose-700': widget.tone === 'rose',
                                    'bg-slate-100 text-slate-700': widget.tone === 'slate'
                                }" x-text="widget.label.charAt(0)" aria-hidden="true"></span>
                    </div>
                    <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                        <p class="text-xs text-slate-500"
                            x-text="widget.error ? 'Không tải được dữ liệu' : widget.caption"></p>
                        <span x-show="widget.error" class="text-xs font-semibold text-rose-600">Thử lại</span>
                    </div>
                </article>
            </template>

            <div x-show="!refreshing && widgets.length === 0" class="surface-card col-span-full p-8 text-center">
                <p class="font-semibold text-slate-800">Chưa có chỉ số phù hợp với quyền hiện tại</p>
                <p class="mt-1 text-sm text-slate-500">Liên hệ quản trị viên nếu bạn cần truy cập thêm module.</p>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <section class="surface-card overflow-hidden" x-show="canViewAppointments">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                <div>
                    <h2 class="font-bold text-slate-900">Lịch hẹn hôm nay</h2>
                    <p class="mt-1 text-sm text-slate-500">Tối đa 8 cuộc hẹn gần nhất trong ngày.</p>
                </div>
                <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700"
                    x-text="`${appointments.length} lịch`"></span>
            </div>

            <div x-show="appointmentsLoading" class="space-y-3 p-6" role="status">
                <template x-for="index in 4" :key="index">
                    <div class="h-12 animate-pulse rounded-xl bg-slate-100"></div>
                </template>
            </div>

            <div x-cloak x-show="!appointmentsLoading && appointmentsError" class="p-8 text-center">
                <p class="font-semibold text-rose-700">Không thể tải lịch hẹn</p>
                <p class="mt-1 text-sm text-slate-500" x-text="appointmentsError"></p>
                <button type="button" class="mt-4 text-sm font-semibold text-blue-600 hover:text-blue-700"
                    x-on:click="loadAppointments()">Thử lại</button>
            </div>

            <div x-cloak x-show="!appointmentsLoading && !appointmentsError && appointments.length === 0"
                class="p-10 text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-500">
                    <x-ui.icon name="calendar" />
                </span>
                <p class="mt-4 font-semibold text-slate-800">Hôm nay chưa có lịch hẹn</p>
                <p class="mt-1 text-sm text-slate-500">Các lịch mới sẽ xuất hiện tại đây.</p>
            </div>

            <div x-cloak x-show="!appointmentsLoading && !appointmentsError && appointments.length > 0"
                class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            <th class="px-5 py-3 sm:px-6">Giờ</th>
                            <th class="px-5 py-3 sm:px-6">Bệnh nhân</th>
                            <th class="hidden px-5 py-3 md:table-cell sm:px-6">Bác sĩ</th>
                            <th class="px-5 py-3 sm:px-6">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <template x-for="appointment in appointments" :key="appointment.id">
                            <tr class="hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-900 sm:px-6"
                                    x-text="formatTime(appointment.scheduled_at)"></td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-sm font-semibold text-slate-800"
                                        x-text="appointment.patient?.full_name ?? 'Chưa có thông tin'"></p>
                                    <p class="mt-0.5 max-w-xs truncate text-xs text-slate-500"
                                        x-text="appointment.reason || 'Không ghi lý do'"></p>
                                </td>
                                <td class="hidden px-5 py-4 text-sm text-slate-600 md:table-cell sm:px-6"
                                    x-text="appointment.doctor?.user?.name ?? '—'"></td>
                                <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                        :class="statusClasses(appointment.status)"
                                        x-text="statusLabel(appointment.status)"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="surface-card h-fit p-5 sm:p-6">
            <h2 class="font-bold text-slate-900">Thao tác nhanh</h2>
            <p class="mt-1 text-sm text-slate-500">Lối tắt tới các màn hình nghiệp vụ theo quyền của bạn.</p>

            <div class="mt-5 space-y-2">
                <a x-show="canCreateAppointment" href="{{ route('web.appointments.index') }}"
                    class="flex w-full items-center gap-3 rounded-xl border border-slate-200 p-3 text-left transition hover:border-blue-200 hover:bg-blue-50">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-blue-100 text-blue-700">
                        <x-ui.icon name="calendar" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-slate-800">Tạo lịch hẹn</span>
                        <span class="block text-xs text-slate-500">Quản lý lịch hẹn khám bệnh</span>
                    </span>
                    <x-ui.icon name="arrow-right" size="h-4 w-4" class="text-slate-400" />
                </a>

                <a x-show="canCreatePatient" href="{{ route('web.patients.index') }}"
                    class="flex w-full items-center gap-3 rounded-xl border border-slate-200 p-3 text-left transition hover:border-teal-200 hover:bg-teal-50">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-teal-100 text-teal-700">
                        <x-ui.icon name="patients" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-slate-800">Thêm bệnh nhân</span>
                        <span class="block text-xs text-slate-500">Quản lý hồ sơ bệnh nhân</span>
                    </span>
                    <x-ui.icon name="arrow-right" size="h-4 w-4" class="text-slate-400" />
                </a>

                <a x-show="canCreateInvoice" href="{{ route('web.invoices.index') }}"
                    class="flex w-full items-center gap-3 rounded-xl border border-slate-200 p-3 text-left transition hover:border-amber-200 hover:bg-amber-50">
                    <span class="grid h-9 w-9 place-items-center rounded-lg bg-amber-100 text-amber-700">
                        <x-ui.icon name="invoice" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-slate-800">Tạo hóa đơn</span>
                        <span class="block text-xs text-slate-500">Quản lý danh sách hóa đơn</span>
                    </span>
                    <x-ui.icon name="arrow-right" size="h-4 w-4" class="text-slate-400" />
                </a>
            </div>

            <div class="mt-5 rounded-xl bg-slate-50 p-4">
                <div class="flex gap-3">
                    <span class="mt-0.5 text-blue-600">
                        <x-ui.icon name="clock" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Triển khai theo checklist</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Mỗi module sẽ được mở khi hoàn thành đủ
                            loading, error, permission và responsive.</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection