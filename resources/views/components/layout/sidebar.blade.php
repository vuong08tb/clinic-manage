@php
$groups = [
[
'label' => 'Tổng quan',
'items' => [
['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('dashboard'), 'active' =>
request()->routeIs('dashboard'), 'permissions' => [], 'ready' => true],
],
],
[
'label' => 'Tiếp đón',
'items' => [
['label' => 'Bệnh nhân','icon' => 'patients','href' => route('web.patients.index'),
'active' => request()->routeIs('web.patients.*'),'permissions' => ['PATIENTS.FINDALL'],'ready' => true],
['label' => 'Lịch hẹn', 'icon' => 'calendar', 'href' => route('web.appointments.index'),
'active' => request()->routeIs('web.appointments.*'), 'permissions' => ['APPOINTMENTS.FINDALL'], 'ready' => true],
],
],
[
'label' => 'Khám bệnh',
'items' => [
['label' => 'Phiếu khám', 'icon' => 'examination', 'href' => route('web.examinations.index'),
'active' => request()->routeIs('web.examinations.*'), 'permissions' => ['EXAMINATIONS.FINDALL'], 'ready' => true],
['label' => 'Toa thuốc', 'icon' => 'prescription', 'href' => route('web.prescriptions.index'),
'active' => request()->routeIs('web.prescriptions.*'), 'permissions' => ['PRESCRIPTIONS.FINDALL'], 'ready' => true],
],
],
[
'label' => 'Dược',
'items' => [
['label' => 'Kho thuốc', 'icon' => 'medicine', 'href' => route('web.medicines.index'),
'active' => request()->routeIs('web.medicines.*'), 'permissions' => ['MEDICINES.FINDALL'], 'ready' => true],
],
],
[
'label' => 'Tài chính',
'items' => [
['label' => 'Hóa đơn', 'icon' => 'invoice', 'href' => route('web.invoices.index'),
'active' => request()->routeIs('web.invoices.*'), 'permissions' => ['INVOICES.FINDALL'], 'ready' => true],
],
],
[
'label' => 'Danh mục',
'items' => [
['label' => 'Chuyên khoa', 'icon' => 'specialty', 'href' => route('web.specialties.index'),
'active' => request()->routeIs('web.specialties.*'), 'permissions' => ['SPECIALTIES.FINDALL'], 'ready' => true],
['label' => 'Bác sĩ', 'icon' => 'doctor', 'href' => route('web.doctors.index'),
'active' => request()->routeIs('web.doctors.*'), 'permissions' => ['DOCTORS.FINDALL'], 'ready' => true],
],
],
[
'label' => 'Hệ thống',
'items' => [
['label' => 'Người dùng', 'icon' => 'users', 'href' => route('web.users.index'),
'active' => request()->routeIs('web.users.*'), 'permissions' => ['USERS.FINDALL'], 'ready' => true],
],
],
];
@endphp

<div x-cloak x-show="$store.ui.sidebarOpen" x-transition.opacity class="fixed inset-0 z-30 bg-slate-950/40 lg:hidden"
    x-on:click="$store.ui.closeSidebar()" aria-hidden="true"></div>

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-60 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0"
    :class="{ 'translate-x-0': $store.ui.sidebarOpen }" aria-label="Điều hướng chính">
    <div class="flex h-16 items-center justify-between border-b border-slate-100 px-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg">
            <span
                class="grid h-9 w-9 place-items-center rounded-xl bg-blue-600 text-white shadow-sm shadow-blue-600/20">
                <x-ui.icon name="clinic" />
            </span>
            <span>
                <span class="block text-sm font-bold text-slate-950">Clinic Admin</span>
                <span class="block text-xs text-slate-500">Quản lý phòng khám</span>
            </span>
        </a>

        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
            x-on:click="$store.ui.closeSidebar()" aria-label="Đóng thanh điều hướng">
            <x-ui.icon name="close" />
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        @foreach ($groups as $group)
        @php
        $groupPermissions = collect($group['items'])->pluck('permissions')->flatten()->unique()->values()->all();
        @endphp
        <section @if ($groupPermissions !==[]) x-show="$store.auth.canAny(@js($groupPermissions))" @endif>
            <p class="px-3 text-[11px] font-bold tracking-wider text-slate-400 uppercase">{{ $group['label'] }}</p>
            <div class="mt-2 space-y-1">
                @foreach ($group['items'] as $item)
                @if ($item['ready'])
                <a href="{{ $item['href'] }}" @if ($item['permissions'] !==[])
                    x-show="$store.auth.canAny(@js($item['permissions']))" @endif
                    @class([ 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition'
                    , 'bg-blue-50 text-blue-700'=> $item['active'],
                    'text-slate-600 hover:bg-slate-100 hover:text-slate-950' => ! $item['active'],
                    ])
                    x-on:click="$store.ui.closeSidebar()"
                    >
                    <x-ui.icon :name="$item['icon']" />
                    <span>{{ $item['label'] }}</span>
                </a>
                @else
                <span x-show="$store.auth.canAny(@js($item['permissions']))"
                    class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-400"
                    title="Màn hình sẽ được triển khai ở task tiếp theo">
                    <x-ui.icon :name="$item['icon']" />
                    <span>{{ $item['label'] }}</span>
                    <span class="ml-auto text-[10px] font-semibold tracking-wide text-slate-400 uppercase">Sắp có</span>
                </span>
                @endif
                @endforeach
            </div>
        </section>
        @endforeach
    </nav>

    <div class="border-t border-slate-100 p-4">
        <div class="rounded-xl bg-slate-50 p-3">
            <p class="text-xs font-medium text-slate-500">Phiên đăng nhập</p>
            <p class="mt-1 truncate text-sm font-semibold text-slate-800" x-text="$store.auth.roleName"></p>
        </div>
    </div>
</aside>