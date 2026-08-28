<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">

        <title>@yield('title', 'Dashboard') · {{ config('app.name', 'Clinic Management') }}</title>

        <meta name="paypal-mode" content="{{ config('paypal.mode') }}">
        <meta name="paypal-currency" content="{{ config('paypal.currency') }}">
        <meta name="paypal-exchange-rate-vnd" content="{{ config('paypal.exchange_rate_vnd') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50" x-data x-init="$store.auth.bootstrap()">
        <div
            x-show="!$store.auth.ready"
            class="fixed inset-0 z-50 grid place-items-center bg-white"
            role="status"
            aria-label="Đang xác thực phiên đăng nhập"
        >
            <div class="text-center">
                <span class="mx-auto block h-9 w-9 animate-spin rounded-full border-4 border-blue-100 border-t-blue-600"></span>
                <p class="mt-4 text-sm font-medium text-slate-500">Đang chuẩn bị không gian làm việc…</p>
            </div>
        </div>

        <div x-cloak x-show="$store.auth.ready && $store.auth.user" class="min-h-screen">
            <x-layout.sidebar />

            <div class="min-h-screen lg:pl-60">
                <x-layout.topbar />

                <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    @yield('content')
                </main>
            </div>
        </div>

        <x-ui.toast />
    </body>
</html>
