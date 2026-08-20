@extends('layouts.app')

@section('title', 'Người dùng')

@section('content')
<div x-data="userIndexPage" x-on:keydown.escape.window="handleEscape()"
    class="mx-auto max-w-[1400px] space-y-6">
    <x-layout.page-header title="Người dùng" description="Quản lý tài khoản nhân viên và vai trò truy cập hệ thống.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="refresh()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />
                <span x-text="refreshing ? 'Đang tải' : 'Làm mới'"></span>
            </x-ui.button>

            <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">
                <x-ui.icon name="users" />
                Thêm người dùng
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    {{-- Filters --}}
    <section class="surface-card p-4 sm:p-5">
        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="flex-1">
                <label for="user-search" class="mb-2 block text-sm font-semibold text-slate-700">Tìm kiếm</label>
                <input id="user-search" type="search" class="form-input" placeholder="Tên hoặc email" maxlength="255"
                    x-model="filters.q" x-on:input.debounce.350ms="search()">
            </div>

            <div class="sm:w-48">
                <label for="user-role" class="mb-2 block text-sm font-semibold text-slate-700">Vai trò</label>
                <select id="user-role" class="form-input" x-model="filters.role_id" x-on:change="applyFilters()">
                    <option value="">Tất cả</option>
                    <template x-for="role in roleOptions" :key="role.id">
                        <option :value="role.id" x-text="role.display_name"></option>
                    </template>
                </select>
            </div>

            <div class="sm:w-48">
                <label for="user-status" class="mb-2 block text-sm font-semibold text-slate-700">Trạng thái</label>
                <select id="user-status" class="form-input" x-model="filters.is_active" x-on:change="applyFilters()">
                    <option value="">Tất cả</option>
                    <option value="true">Đang hoạt động</option>
                    <option value="false">Đã khóa</option>
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
        <p class="font-semibold text-rose-700">Không thể tải danh sách người dùng</p>
        <p class="mt-2 text-sm text-slate-500" x-text="listError"></p>
        <x-ui.button variant="secondary" class="mt-5" x-on:click="loadList()">Thử lại</x-ui.button>
    </section>

    <section x-cloak x-show="!loading && !listError && users.length === 0" class="surface-card">
        <x-ui.empty-state icon="users" title="Không tìm thấy người dùng"
            description="Hãy thử bộ lọc khác hoặc thêm người dùng mới.">
            <x-slot:action>
                <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">Thêm người dùng</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    </section>

    <section x-cloak x-show="!loading && !listError && users.length > 0" class="surface-card overflow-hidden">
        {{-- Mobile cards --}}
        <div class="divide-y divide-slate-100 md:hidden">
            <template x-for="user in users" :key="user.id">
                <article class="space-y-2 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate font-bold text-slate-900" x-text="user.name"></h3>
                            <p class="truncate text-sm text-slate-500" x-text="user.email"></p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                            x-bind:class="statusClasses(user.is_active ? 'active' : 'inactive')"
                            x-text="user.is_active ? 'Hoạt động' : 'Đã khóa'"></span>
                    </div>
                    <p class="text-sm text-slate-600" x-text="user.role?.display_name ?? '—'"></p>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                        <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                            x-on:click="openDetailModal(user)" />
                        <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                            x-on:click="openEditModal(user)" />
                        <x-ui.row-action label="Khóa tài khoản" icon="ban" tone="danger"
                            x-show="canUpdateStatus && user.is_active" x-on:click="askToggleStatus(user)" />
                        <x-ui.row-action label="Mở khóa tài khoản" icon="check" tone="success"
                            x-show="canUpdateStatus && !user.is_active" x-on:click="askToggleStatus(user)" />
                    </div>
                </article>
            </template>
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <th class="px-5 py-3">Người dùng</th>
                        <th class="px-5 py-3">Vai trò</th>
                        <th class="px-5 py-3">Trạng thái</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="user in users" :key="user.id">
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900" x-text="user.name"></p>
                                <p class="mt-1 text-xs text-slate-500" x-text="user.email"></p>
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-700" x-text="user.role?.display_name ?? '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                                    x-bind:class="statusClasses(user.is_active ? 'active' : 'inactive')"
                                    x-text="user.is_active ? 'Hoạt động' : 'Đã khóa'"></span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                                        x-on:click="openDetailModal(user)" />
                                    <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                                        x-on:click="openEditModal(user)" />
                                    <x-ui.row-action label="Khóa tài khoản" icon="ban" tone="danger"
                                        x-show="canUpdateStatus && user.is_active" x-on:click="askToggleStatus(user)" />
                                    <x-ui.row-action label="Mở khóa tài khoản" icon="check" tone="success"
                                        x-show="canUpdateStatus && !user.is_active" x-on:click="askToggleStatus(user)" />
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
                x-text="`Hiển thị ${meta.from ?? 0}–${meta.to ?? 0} trong ${meta.total} người dùng`"></p>
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

    {{-- Detail modal --}}
    <x-ui.modal show="detailOpen" close="closeDetailModal()" id="user-detail" title="Chi tiết người dùng">
        <div x-show="detailLoading" class="space-y-3" role="status">
            <div class="h-5 w-40 animate-pulse rounded bg-slate-100"></div>
            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <p x-cloak x-show="detailError" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            role="alert" x-text="detailError"></p>

        <dl x-cloak x-show="!detailLoading && !detailError && detail" class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-slate-500">Họ và tên</dt>
                <dd class="mt-1 text-lg font-bold text-slate-900" x-text="detail?.name"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Email</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.email"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Vai trò</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.role?.display_name ?? '—'"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Trạng thái</dt>
                <dd class="mt-1">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
                        x-bind:class="statusClasses(detail?.is_active ? 'active' : 'inactive')"
                        x-text="detail?.is_active ? 'Hoạt động' : 'Đã khóa'"></span>
                </dd>
            </div>
        </dl>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeDetailModal()">Đóng</x-ui.button>

            <x-ui.button variant="secondary" x-show="canUpdateStatus" x-on:click="askToggleStatus(detail)">
                <span x-text="detail?.is_active ? 'Khóa tài khoản' : 'Mở khóa tài khoản'"></span>
            </x-ui.button>

            <x-ui.button x-show="canUpdate" x-on:click="editFromDetail()">Sửa người dùng</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Create/update modal --}}
    <x-ui.modal show="formOpen" close="closeFormModal()" id="user-form-modal"
        title-expr="formMode === 'create' ? 'Thêm người dùng' : 'Cập nhật người dùng'">
        <form id="user-form" x-on:submit.prevent="submitForm()" novalidate class="space-y-5">
            <div x-cloak x-show="formMessage"
                class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"
                x-text="formMessage"></div>

            <div>
                <label for="user-name" class="mb-2 block text-sm font-semibold text-slate-700">
                    Họ và tên <span class="text-rose-600">*</span>
                </label>
                <input id="user-name" type="text" class="form-input" maxlength="255" required
                    x-model.trim="form.name" x-bind:class="{ 'form-input-error': fieldError('name') }">
                <p x-cloak x-show="fieldError('name')" class="mt-1.5 text-sm text-rose-600" x-text="fieldError('name')"></p>
            </div>

            <div>
                <label for="user-email" class="mb-2 block text-sm font-semibold text-slate-700">
                    Email <span class="text-rose-600">*</span>
                </label>
                <input id="user-email" type="email" class="form-input" maxlength="255" autocomplete="off" required
                    x-model.trim="form.email" x-bind:class="{ 'form-input-error': fieldError('email') }">
                <p x-cloak x-show="fieldError('email')" class="mt-1.5 text-sm text-rose-600" x-text="fieldError('email')"></p>
            </div>

            <div>
                <label for="user-role-select" class="mb-2 block text-sm font-semibold text-slate-700">
                    Vai trò <span class="text-rose-600">*</span>
                </label>
                <select id="user-role-select" class="form-input" x-model="form.role_id"
                    x-bind:class="{ 'form-input-error': fieldError('role_id') }">
                    <option value="">Chọn vai trò</option>
                    <template x-for="role in roleOptions" :key="role.id">
                        <option :value="role.id" x-text="role.display_name"></option>
                    </template>
                </select>
                <p x-cloak x-show="fieldError('role_id')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('role_id')"></p>
            </div>

            <template x-if="formMode === 'edit'">
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300" x-model="form.changePassword">
                    Đổi mật khẩu
                </label>
            </template>

            <div x-show="formMode === 'create' || form.changePassword" class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="user-password" class="mb-2 block text-sm font-semibold text-slate-700">
                        Mật khẩu <span class="text-rose-600">*</span>
                    </label>
                    <input id="user-password" type="password" class="form-input" autocomplete="new-password"
                        x-model="form.password" x-bind:class="{ 'form-input-error': fieldError('password') }">
                    <p x-cloak x-show="fieldError('password')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('password')"></p>
                </div>

                <div>
                    <label for="user-password-confirmation" class="mb-2 block text-sm font-semibold text-slate-700">
                        Xác nhận mật khẩu <span class="text-rose-600">*</span>
                    </label>
                    <input id="user-password-confirmation" type="password" class="form-input"
                        autocomplete="new-password" x-model="form.password_confirmation">
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeFormModal()" x-bind:disabled="submitting">Hủy</x-ui.button>
            <x-ui.button type="submit" form="user-form" x-bind:disabled="submitting">
                <span x-show="submitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    aria-hidden="true"></span>
                <span x-text="submitting ? 'Đang lưu…' : 'Lưu người dùng'"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Confirm popup: khóa/mở khóa tài khoản --}}
    <x-ui.confirm-modal show="statusTarget" cancel="cancelToggleStatus()" confirm="confirmToggleStatus()"
        id="user-status" title-expr="statusTarget?.is_active ? 'Khóa tài khoản?' : 'Mở khóa tài khoản?'"
        busy="statusBusy" confirm-label="Xác nhận" busy-label="Đang xử lý…">
        <p>
            Tài khoản <strong x-text="statusTarget?.name"></strong>
            <span x-show="statusTarget?.is_active">sẽ không thể đăng nhập cho tới khi được mở khóa lại.</span>
            <span x-show="!statusTarget?.is_active">sẽ có thể đăng nhập trở lại.</span>
        </p>
        <p x-show="isSelf(statusTarget)" class="text-xs text-amber-600">
            Đây là tài khoản bạn đang đăng nhập — thao tác này có thể khiến bạn bị đăng xuất.
        </p>
    </x-ui.confirm-modal>
</div>
@endsection
