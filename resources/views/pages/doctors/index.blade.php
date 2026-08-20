@extends('layouts.app')

@section('title', 'Bác sĩ')

@section('content')
<div x-data="doctorIndexPage" x-on:keydown.escape.window="handleEscape()"
    class="mx-auto max-w-[1400px] space-y-6">
    <x-layout.page-header title="Bác sĩ" description="Hồ sơ bác sĩ gắn với tài khoản người dùng và chuyên khoa.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="refresh()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />
                <span x-text="refreshing ? 'Đang tải' : 'Làm mới'"></span>
            </x-ui.button>

            <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">
                <x-ui.icon name="doctor" />
                Thêm bác sĩ
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    {{-- Filters --}}
    <section class="surface-card p-4 sm:p-5">
        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="flex-1">
                <label for="doctor-search" class="mb-2 block text-sm font-semibold text-slate-700">Tìm kiếm</label>
                <input id="doctor-search" type="search" class="form-input"
                    placeholder="Tên, email, số CCHN hoặc tiểu sử" maxlength="255" x-model="filters.q"
                    x-on:input.debounce.350ms="search()">
            </div>

            <div class="sm:w-56">
                <label for="doctor-specialty" class="mb-2 block text-sm font-semibold text-slate-700">Chuyên khoa</label>
                <select id="doctor-specialty" class="form-input" x-model="filters.specialty_id"
                    x-on:change="applyFilters()">
                    <option value="">Tất cả</option>
                    <template x-for="specialty in specialtyOptions" :key="specialty.id">
                        <option :value="specialty.id" x-text="specialty.name"></option>
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
        <p class="font-semibold text-rose-700">Không thể tải danh sách bác sĩ</p>
        <p class="mt-2 text-sm text-slate-500" x-text="listError"></p>
        <x-ui.button variant="secondary" class="mt-5" x-on:click="loadList()">Thử lại</x-ui.button>
    </section>

    <section x-cloak x-show="!loading && !listError && doctors.length === 0" class="surface-card">
        <x-ui.empty-state icon="doctor" title="Không tìm thấy bác sĩ"
            description="Hãy thử bộ lọc khác hoặc thêm bác sĩ mới.">
            <x-slot:action>
                <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">Thêm bác sĩ</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    </section>

    <section x-cloak x-show="!loading && !listError && doctors.length > 0" class="surface-card overflow-hidden">
        {{-- Mobile cards --}}
        <div class="divide-y divide-slate-100 md:hidden">
            <template x-for="doctor in doctors" :key="doctor.id">
                <article class="space-y-2 p-4">
                    <h3 class="font-bold text-slate-900" x-text="`BS. ${doctor.user?.name ?? '—'}`"></h3>
                    <p class="text-sm text-slate-500" x-text="doctor.user?.email"></p>
                    <p class="text-sm text-slate-600" x-text="doctor.specialty?.name ?? '—'"></p>
                    <p class="text-xs text-slate-500" x-text="`CCHN: ${doctor.license_number}`"></p>
                    <p class="truncate text-xs text-slate-500" x-text="doctor.bio || 'Không có tiểu sử'"></p>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                        <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                            x-on:click="openDetailModal(doctor)" />
                        <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                            x-on:click="openEditModal(doctor)" />
                        <x-ui.row-action label="Xóa" icon="trash" tone="danger" x-show="canDelete"
                            x-on:click="askDelete(doctor)" />
                    </div>
                </article>
            </template>
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <th class="px-5 py-3">Bác sĩ</th>
                        <th class="px-5 py-3">Chuyên khoa</th>
                        <th class="px-5 py-3">Số CCHN</th>
                        <th class="px-5 py-3">Tiểu sử</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="doctor in doctors" :key="doctor.id">
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900" x-text="`BS. ${doctor.user?.name ?? '—'}`"></p>
                                <p class="mt-1 text-xs text-slate-500" x-text="doctor.user?.email"></p>
                            </td>
                            <td class="px-5 py-4 text-sm text-slate-700" x-text="doctor.specialty?.name ?? '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600" x-text="doctor.license_number"></td>
                            <td class="max-w-xs truncate px-5 py-4 text-sm text-slate-600"
                                x-text="doctor.bio || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                                        x-on:click="openDetailModal(doctor)" />
                                    <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                                        x-on:click="openEditModal(doctor)" />
                                    <x-ui.row-action label="Xóa" icon="trash" tone="danger" x-show="canDelete"
                                        x-on:click="askDelete(doctor)" />
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
                x-text="`Hiển thị ${meta.from ?? 0}–${meta.to ?? 0} trong ${meta.total} bác sĩ`"></p>
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
    <x-ui.modal show="detailOpen" close="closeDetailModal()" id="doctor-detail" title="Chi tiết bác sĩ">
        <div x-show="detailLoading" class="space-y-3" role="status">
            <div class="h-5 w-40 animate-pulse rounded bg-slate-100"></div>
            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <p x-cloak x-show="detailError" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            role="alert" x-text="detailError"></p>

        <dl x-cloak x-show="!detailLoading && !detailError && detail" class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-slate-500">Bác sĩ</dt>
                <dd class="mt-1 text-lg font-bold text-slate-900" x-text="`BS. ${detail?.user?.name ?? '—'}`"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Email</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.user?.email"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Chuyên khoa</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.specialty?.name ?? '—'"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Số CCHN</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.license_number"></dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-slate-500">Tiểu sử</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-700" x-text="detail?.bio || 'Không có tiểu sử.'"></dd>
            </div>
        </dl>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeDetailModal()">Đóng</x-ui.button>
            <x-ui.button x-show="canUpdate" x-on:click="editFromDetail()">Sửa bác sĩ</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Create/update modal --}}
    <x-ui.modal show="formOpen" close="closeFormModal()" id="doctor-form-modal"
        title-expr="formMode === 'create' ? 'Thêm bác sĩ' : 'Cập nhật bác sĩ'">
        <form id="doctor-form" x-on:submit.prevent="submitForm()" novalidate class="space-y-5">
            <div x-cloak x-show="formMessage"
                class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"
                x-text="formMessage"></div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Tài khoản người dùng <span class="text-rose-600">*</span>
                </label>

                <div x-show="form.user_id" class="flex items-center justify-between rounded-xl border border-slate-300 px-3 py-2.5">
                    <span class="text-sm text-slate-800" x-text="form.user_label"></span>
                    <button type="button" class="text-xs font-semibold text-rose-600" x-on:click="clearUser()">
                        Đổi
                    </button>
                </div>

                <div x-show="!form.user_id" class="relative">
                    <input type="text" class="form-input" placeholder="Tìm theo tên hoặc email"
                        x-model="userQuery" x-on:input.debounce.350ms="searchUsers()"
                        x-bind:class="{ 'form-input-error': fieldError('user_id') }">

                    <ul x-show="userResults.length > 0"
                        class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                        <template x-for="user in userResults" :key="user.id">
                            <li>
                                <button type="button" x-on:click="selectUser(user)"
                                    class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">
                                    <span class="font-semibold" x-text="user.name"></span>
                                    <span class="ml-1 text-xs text-slate-500" x-text="`(${user.email})`"></span>
                                </button>
                            </li>
                        </template>
                    </ul>

                    <p x-show="!searchingUser && userQuery.trim() !== '' && userResults.length === 0"
                        class="mt-2 text-xs text-slate-400">
                        Không tìm thấy tài khoản có vai trò Bác sĩ phù hợp.
                    </p>
                </div>

                <p class="mt-1.5 text-xs text-slate-400">Chỉ hiện tài khoản đang có vai trò Bác sĩ.</p>
                <p x-cloak x-show="fieldError('user_id')" class="mt-1.5 text-sm text-rose-600" x-text="fieldError('user_id')"></p>
            </div>

            <div>
                <label for="doctor-specialty-select" class="mb-2 block text-sm font-semibold text-slate-700">
                    Chuyên khoa <span class="text-rose-600">*</span>
                </label>
                <select id="doctor-specialty-select" class="form-input" x-model="form.specialty_id"
                    x-bind:class="{ 'form-input-error': fieldError('specialty_id') }">
                    <option value="">Chọn chuyên khoa</option>
                    <template x-for="specialty in specialtyOptions" :key="specialty.id">
                        <option :value="specialty.id" x-text="specialty.name"></option>
                    </template>
                </select>
                <p x-cloak x-show="fieldError('specialty_id')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('specialty_id')"></p>
            </div>

            <div>
                <label for="doctor-license" class="mb-2 block text-sm font-semibold text-slate-700">
                    Số chứng chỉ hành nghề <span class="text-rose-600">*</span>
                </label>
                <input id="doctor-license" type="text" class="form-input" maxlength="255" required
                    x-model.trim="form.license_number" x-bind:class="{ 'form-input-error': fieldError('license_number') }">
                <p x-cloak x-show="fieldError('license_number')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('license_number')"></p>
            </div>

            <div>
                <label for="doctor-bio" class="mb-2 block text-sm font-semibold text-slate-700">Tiểu sử</label>
                <textarea id="doctor-bio" rows="4" class="form-input" maxlength="5000" x-model.trim="form.bio"
                    x-bind:class="{ 'form-input-error': fieldError('bio') }"></textarea>
                <p x-cloak x-show="fieldError('bio')" class="mt-1.5 text-sm text-rose-600" x-text="fieldError('bio')"></p>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeFormModal()" x-bind:disabled="submitting">Hủy</x-ui.button>
            <x-ui.button type="submit" form="doctor-form" x-bind:disabled="submitting">
                <span x-show="submitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    aria-hidden="true"></span>
                <span x-text="submitting ? 'Đang lưu…' : 'Lưu bác sĩ'"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Delete confirmation popup --}}
    <x-ui.confirm-modal show="deleteTarget" cancel="cancelDelete()" confirm="confirmDelete()" id="doctor-delete"
        title="Xóa bác sĩ?" busy="deleting" confirm-label="Xóa bác sĩ" busy-label="Đang xóa…">
        <p>
            Hồ sơ bác sĩ <strong x-text="deleteTarget?.user?.name"></strong> sẽ bị xóa. Không thể xóa nếu bác sĩ còn
            lịch hẹn, phiếu khám hoặc toa thuốc liên quan.
        </p>
    </x-ui.confirm-modal>
</div>
@endsection
