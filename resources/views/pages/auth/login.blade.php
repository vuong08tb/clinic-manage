@extends('layouts.guest')

@section('title', 'Đăng nhập')

@section('content')
<main class="relative grid min-h-screen overflow-hidden lg:grid-cols-[minmax(0,1fr)_minmax(480px,0.78fr)]"
    x-data="loginPage">
    <section class="relative hidden overflow-hidden bg-blue-700 p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="absolute inset-0 opacity-40" aria-hidden="true">
            <div class="absolute -top-32 -left-24 h-96 w-96 rounded-full bg-cyan-300/30 blur-3xl"></div>
            <div
                class="absolute right-0 bottom-0 h-[32rem] w-[32rem] translate-x-1/3 translate-y-1/3 rounded-full bg-indigo-950/40 blur-3xl">
            </div>
        </div>

        <div class="relative flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white/15 ring-1 ring-white/30 backdrop-blur">
                <x-ui.icon name="clinic" size="h-6 w-6" />
            </span>
            <div>
                <p class="text-base font-bold">Clinic Management</p>
                <p class="text-sm text-blue-100">Hệ thống quản lý phòng khám</p>
            </div>
        </div>

        <div class="relative max-w-xl">
            <p class="text-sm font-semibold tracking-widest text-cyan-200 uppercase">Không gian làm việc nội bộ</p>
            <h1 class="mt-5 text-4xl leading-tight font-bold tracking-tight xl:text-5xl">
                Mọi quy trình phòng khám trong một giao diện rõ ràng.
            </h1>
            <p class="mt-6 max-w-lg text-base leading-7 text-blue-100">
                Theo dõi lịch hẹn, hồ sơ bệnh nhân, khám bệnh, kho thuốc và thanh toán theo đúng vai trò của bạn.
            </p>
        </div>

        <p class="relative text-sm text-blue-200">Dữ liệu được bảo vệ theo quyền truy cập của từng nhân viên.</p>
    </section>

    <section class="flex min-h-screen items-center justify-center bg-white px-5 py-10 sm:px-10 lg:bg-slate-50">
        <div class="w-full max-w-md">
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-blue-600 text-white">
                    <x-ui.icon name="clinic" />
                </span>
                <div>
                    <p class="font-bold text-slate-950">Clinic Management</p>
                    <p class="text-xs text-slate-500">Quản lý phòng khám</p>
                </div>
            </div>

            <div x-show="checkingSession" class="py-20 text-center" role="status">
                <span
                    class="mx-auto block h-9 w-9 animate-spin rounded-full border-4 border-blue-100 border-t-blue-600"></span>
                <p class="mt-4 text-sm text-slate-500">Đang kiểm tra phiên đăng nhập…</p>
            </div>

            <div x-cloak x-show="!checkingSession" x-transition.opacity>
                <p class="text-sm font-semibold text-blue-600">Chào mừng trở lại</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Đăng nhập tài khoản</h2>
                <p class="mt-3 text-sm leading-6 text-slate-500">
                    Sử dụng tài khoản nhân viên đã được quản trị viên cấp.
                </p>

                <div x-cloak x-show="message"
                    class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                    role="alert" x-text="message"></div>

                <form class="mt-8 space-y-5" x-on:submit.prevent="submit" novalidate>
                    <div>
                        <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email</label>
                        <input id="email" name="email" type="email" autocomplete="username" inputmode="email" required
                            class="form-input" :class="{ 'form-input-error': fieldError('email') }"
                            x-model.trim="form.email" placeholder="name@clinic.vn"
                            :aria-invalid="Boolean(fieldError('email'))" aria-describedby="email-error">
                        <p id="email-error" x-cloak x-show="fieldError('email')" class="mt-1.5 text-sm text-rose-600"
                            x-text="fieldError('email')"></p>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label for="password" class="block text-sm font-semibold text-slate-700">Mật khẩu</label>
                        </div>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                            class="form-input" :class="{ 'form-input-error': fieldError('password') }"
                            x-model="form.password" placeholder="Nhập mật khẩu"
                            :aria-invalid="Boolean(fieldError('password'))" aria-describedby="password-error">
                        <p id="password-error" x-cloak x-show="fieldError('password')"
                            class="mt-1.5 text-sm text-rose-600" x-text="fieldError('password')"></p>
                    </div>

                    <x-ui.button type="submit" class="w-full py-3" x-bind:disabled="submitting">
                        <span x-show="submitting"
                            class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                            aria-hidden="true"></span>
                        <span x-text="submitting ? 'Đang đăng nhập…' : 'Đăng nhập'"></span>
                    </x-ui.button>
                </form>

                <p class="mt-8 text-center text-xs leading-5 text-slate-400">
                    Nếu không thể đăng nhập, hãy liên hệ quản trị viên phòng khám.
                </p>
            </div>
        </div>
    </section>
</main>
@endsection