<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">

        <title>@yield('title', 'Đăng nhập') · {{ config('app.name', 'Clinic Management') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50">
        @yield('content')
        <x-ui.toast />
    </body>
</html>
