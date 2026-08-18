<header class="sticky top-0 z-20 flex h-16 items-center border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
    <button
        type="button"
        class="mr-3 rounded-xl p-2 text-slate-600 hover:bg-slate-100 lg:hidden"
        x-on:click="$store.ui.toggleSidebar()"
        aria-label="Mở thanh điều hướng"
    >
        <x-ui.icon name="menu" />
    </button>

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-slate-900">Hệ thống quản lý phòng khám</p>
        <p class="hidden text-xs text-slate-500 sm:block">Không gian làm việc nội bộ</p>
    </div>

    <div class="flex items-center gap-2">
        <button
            type="button"
            class="relative rounded-xl p-2.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800"
            aria-label="Thông báo"
            x-on:click="$store.ui.notify('Trung tâm thông báo sẽ được triển khai ở task sau.')"
        >
            <x-ui.icon name="bell" />
        </button>

        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
            <button
                type="button"
                class="flex items-center gap-2 rounded-xl p-1.5 pr-2 hover:bg-slate-100"
                x-on:click="open = !open"
                :aria-expanded="open"
                aria-haspopup="menu"
            >
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-blue-100 text-xs font-bold text-blue-700" x-text="$store.auth.initials"></span>
                <span class="hidden max-w-36 text-left sm:block">
                    <span class="block truncate text-sm font-semibold text-slate-800" x-text="$store.auth.user?.name"></span>
                    <span class="block truncate text-xs text-slate-500" x-text="$store.auth.roleName"></span>
                </span>
                <x-ui.icon name="chevron-down" class="h-4 w-4 text-slate-400" />
            </button>

            <div
                x-cloak
                x-show="open"
                x-transition.origin.top.right
                class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
                role="menu"
            >
                <div class="border-b border-slate-100 px-3 py-2 sm:hidden">
                    <p class="truncate text-sm font-semibold" x-text="$store.auth.user?.name"></p>
                    <p class="truncate text-xs text-slate-500" x-text="$store.auth.roleName"></p>
                </div>
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-rose-600 hover:bg-rose-50"
                    x-on:click="$store.auth.logout()"
                    role="menuitem"
                >
                    <x-ui.icon name="logout" />
                    Đăng xuất
                </button>
            </div>
        </div>
    </div>
</header>
