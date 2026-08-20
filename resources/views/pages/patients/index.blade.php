@extends('layouts.app')

@section('title', 'Bệnh nhân')

@section('content')
<div x-data="patientIndexPage" x-on:keydown.escape.window="handleEscape()"
    class="mx-auto max-w-[1600px] space-y-6">
    <x-layout.page-header title="Bệnh nhân" description="Quản lý hồ sơ và thông tin liên hệ của bệnh nhân.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="refresh()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />

                <span x-text="refreshing ? 'Đang tải' : 'Làm mới'"></span>
            </x-ui.button>

            <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">
                <x-ui.icon name="patients" />
                Thêm bệnh nhân
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <section class="surface-card p-4 sm:p-5">
        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="flex-1">
                <label for="patient-search" class="mb-2 block text-sm font-semibold text-slate-700">
                    Tìm kiếm
                </label>

                <input id="patient-search" type="search" class="form-input"
                    placeholder="Tên, số điện thoại hoặc mã bệnh nhân" maxlength="255" x-model="filters.q"
                    x-on:input.debounce.350ms="search()">
            </div>

            <div class="sm:w-40">
                <label for="patient-per-page" class="mb-2 block text-sm font-semibold text-slate-700">
                    Số dòng
                </label>

                <select id="patient-per-page" class="form-input" x-model.number="filters.per_page"
                    x-on:change="changePerPage()">
                    <option value="10">10 dòng</option>
                    <option value="15">15 dòng</option>
                    <option value="25">25 dòng</option>
                    <option value="50">50 dòng</option>
                </select>
            </div>
        </div>
    </section>

    <section x-show="loading" class="surface-card space-y-3 p-6" role="status"
        aria-label="Đang tải danh sách bệnh nhân">
        <template x-for="index in 6" :key="index">
            <div class="h-14 animate-pulse rounded-xl bg-slate-100"></div>
        </template>
    </section>

    <section x-cloak x-show="!loading && listError" class="surface-card p-10 text-center">
        <p class="font-semibold text-rose-700">
            Không thể tải danh sách bệnh nhân
        </p>

        <p class="mt-2 text-sm text-slate-500" x-text="listError"></p>

        <x-ui.button variant="secondary" class="mt-5" x-on:click="loadPatients()">
            Thử lại
        </x-ui.button>
    </section>

    <section x-cloak x-show="
                !loading
                && !listError
                && patients.length === 0
            " class="surface-card">
        <x-ui.empty-state icon="patients" title="Không tìm thấy bệnh nhân"
            description="Hãy thử từ khóa khác hoặc thêm bệnh nhân mới.">
            <x-slot:action>
                <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">
                    Thêm bệnh nhân
                </x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    </section>

    <section x-cloak x-show="
                !loading
                && !listError
                && patients.length > 0
            " class="surface-card overflow-hidden">
        {{-- Mobile cards --}}
        <div class="divide-y divide-slate-100 md:hidden">
            <template x-for="patient in patients" :key="patient.id">
                <article class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-blue-600" x-text="patient.code"></p>

                            <h3 class="mt-1 truncate font-bold text-slate-900" x-text="patient.full_name"></h3>
                        </div>

                        <span
                            class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"
                            x-text="genderLabel(patient.gender)"></span>
                    </div>

                    <div class="space-y-1 text-sm text-slate-600">
                        <p>
                            <span class="font-medium">
                                Ngày sinh:
                            </span>

                            <span x-text="formatDateOnly(patient.date_of_birth)"></span>
                        </p>

                        <p>
                            <span class="font-medium">
                                Điện thoại:
                            </span>

                            <span x-text="patient.phone"></span>
                        </p>

                        <p class="truncate" x-text="patient.email || 'Chưa có email'"></p>

                        <p class="truncate" x-text="patient.address || 'Chưa có địa chỉ'"></p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                        <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                            x-on:click="openDetailModal(patient)" />

                        <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                            x-on:click="openEditModal(patient)" />

                        <x-ui.row-action label="Xóa" icon="trash" tone="danger" x-show="canDelete"
                            x-on:click="askDelete(patient)" />
                    </div>
                </article>
            </template>
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <th class="px-5 py-3">
                            Mã
                        </th>

                        <th class="px-5 py-3">
                            Bệnh nhân
                        </th>

                        <th class="px-5 py-3">
                            Ngày sinh
                        </th>

                        <th class="px-5 py-3">
                            Giới tính
                        </th>

                        <th class="px-5 py-3">
                            Liên hệ
                        </th>
                        <th class="px-5 py-3 text-right">
                            Thao tác
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="patient in patients" :key="patient.id">
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-blue-700"
                                x-text="patient.code"></td>

                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900" x-text="patient.full_name"></p>

                                <p class="mt-1 max-w-xs truncate text-xs text-slate-500"
                                    x-text="patient.address || 'Chưa có địa chỉ'"></p>
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600"
                                x-text="formatDateOnly(patient.date_of_birth)"></td>

                            <td class="px-5 py-4 text-sm text-slate-600" x-text="genderLabel(patient.gender)"></td>

                            <td class="px-5 py-4">
                                <p class="text-sm text-slate-700" x-text="patient.phone"></p>

                                <p class="mt-1 max-w-xs truncate text-xs text-slate-500"
                                    x-text="patient.email || 'Chưa có email'"></p>
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                                        x-on:click="openDetailModal(patient)" />

                                    <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                                        x-on:click="openEditModal(patient)" />

                                    <x-ui.row-action label="Xóa" icon="trash" tone="danger" x-show="canDelete"
                                        x-on:click="askDelete(patient)" />
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
                x-text="`Hiển thị ${meta.from ?? 0}–${meta.to ?? 0} trong ${meta.total} bệnh nhân`"></p>

            <div class="flex flex-wrap items-center gap-1">
                <button type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                    x-bind:disabled="meta.current_page <= 1" x-on:click="goToPage(meta.current_page - 1)">
                    Trước
                </button>

                <template x-for="page in visiblePages" :key="page">
                    <button type="button" class="rounded-lg border px-3 py-2 text-sm" x-bind:class="{
                                'border-blue-600 bg-blue-600 text-white':
                                    page === meta.current_page,
                                'border-slate-300 bg-white text-slate-700':
                                    page !== meta.current_page
                            }" x-on:click="goToPage(page)" x-text="page"></button>
                </template>

                <button type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                    x-bind:disabled="
                            meta.current_page >= meta.last_page
                        " x-on:click="
                            goToPage(meta.current_page + 1)
                        ">
                    Sau
                </button>
            </div>
        </div>
    </section>

    {{-- Detail modal --}}
    <x-ui.modal show="detailOpen" close="closeDetailModal()" id="patient-detail" title="Chi tiết bệnh nhân"
        subtitle-expr="detail?.code ? `Mã bệnh nhân: ${detail.code}` : ''">
        <div x-show="detailLoading" class="space-y-3" role="status" aria-label="Đang tải hồ sơ bệnh nhân">
            <div class="h-5 w-40 animate-pulse rounded bg-slate-100"></div>
            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <p x-cloak x-show="detailError" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            role="alert" x-text="detailError"></p>

        <dl x-cloak x-show="!detailLoading && !detailError && detail" class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-slate-500">Họ và tên</dt>
                <dd class="mt-1 text-lg font-bold text-slate-900" x-text="detail?.full_name"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Giới tính</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="genderLabel(detail?.gender)"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Ngày sinh</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="formatDateOnly(detail?.date_of_birth)"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Điện thoại</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.phone"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Email</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.email || 'Chưa có email'"></dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-sm font-medium text-slate-500">Địa chỉ</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="detail?.address || 'Chưa có địa chỉ'"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Ngày tạo hồ sơ</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="formatDate(detail?.created_at)"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Cập nhật gần nhất</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="formatDate(detail?.updated_at)"></dd>
            </div>
        </dl>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeDetailModal()">
                Đóng
            </x-ui.button>

            <x-ui.button x-show="canUpdate" x-on:click="editFromDetail()">
                Sửa bệnh nhân
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Create/update modal --}}
    <x-ui.modal show="formOpen" close="closeFormModal()" id="patient-form-modal"
        title-expr="formMode === 'create' ? 'Thêm bệnh nhân' : 'Cập nhật bệnh nhân'"
        subtitle-expr="formMode === 'edit' ? `Mã bệnh nhân: ${editingCode}` : 'Mã bệnh nhân được hệ thống tự động tạo.'">
        <form id="patient-form" x-on:submit.prevent="submitForm()" novalidate>
            <div x-cloak x-show="formMessage"
                class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"
                x-text="formMessage"></div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="patient-full-name" class="mb-2 block text-sm font-semibold text-slate-700">
                        Họ và tên
                        <span class="text-rose-600">*</span>
                    </label>

                    <input id="patient-full-name" type="text" class="form-input" maxlength="255" required
                        x-model.trim="form.full_name" x-bind:class="{
                                'form-input-error':
                                    fieldError('full_name')
                            }" x-bind:aria-invalid="
                                Boolean(fieldError('full_name'))
                            ">

                    <p x-cloak x-show="fieldError('full_name')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('full_name')"></p>
                </div>

                <div>
                    <label for="patient-gender" class="mb-2 block text-sm font-semibold text-slate-700">
                        Giới tính
                        <span class="text-rose-600">*</span>
                    </label>

                    <select id="patient-gender" class="form-input" required x-model="form.gender" x-bind:class="{
                                'form-input-error':
                                    fieldError('gender')
                            }" x-bind:aria-invalid="
                                Boolean(fieldError('gender'))
                            ">
                        <option value="">
                            Chọn giới tính
                        </option>
                        <option value="male">
                            Nam
                        </option>
                        <option value="female">
                            Nữ
                        </option>
                        <option value="other">
                            Khác
                        </option>
                    </select>

                    <p x-cloak x-show="fieldError('gender')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('gender')"></p>
                </div>

                <div>
                    <label for="patient-date-of-birth" class="mb-2 block text-sm font-semibold text-slate-700">
                        Ngày sinh
                        <span class="text-rose-600">*</span>
                    </label>

                    <input id="patient-date-of-birth" type="date" class="form-input" required
                        x-model="form.date_of_birth" x-bind:max="today" x-bind:class="{
                                'form-input-error':
                                    fieldError('date_of_birth')
                            }" x-bind:aria-invalid="
                                Boolean(fieldError('date_of_birth'))
                            ">

                    <p x-cloak x-show="fieldError('date_of_birth')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('date_of_birth')"></p>
                </div>

                <div>
                    <label for="patient-phone" class="mb-2 block text-sm font-semibold text-slate-700">
                        Số điện thoại
                        <span class="text-rose-600">*</span>
                    </label>

                    <input id="patient-phone" type="tel" class="form-input" maxlength="20" autocomplete="tel" required
                        x-model.trim="form.phone" x-bind:class="{
                                'form-input-error':
                                    fieldError('phone')
                            }" x-bind:aria-invalid="
                                Boolean(fieldError('phone'))
                            ">

                    <p x-cloak x-show="fieldError('phone')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('phone')"></p>
                </div>

                <div>
                    <label for="patient-email" class="mb-2 block text-sm font-semibold text-slate-700">
                        Email
                    </label>

                    <input id="patient-email" type="email" class="form-input" maxlength="255" autocomplete="email"
                        x-model.trim="form.email" x-bind:class="{
                                'form-input-error':
                                    fieldError('email')
                            }" x-bind:aria-invalid="
                                Boolean(fieldError('email'))
                            ">

                    <p x-cloak x-show="fieldError('email')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('email')"></p>
                </div>

                <div class="sm:col-span-2">
                    <label for="patient-address" class="mb-2 block text-sm font-semibold text-slate-700">
                        Địa chỉ
                    </label>

                    <textarea id="patient-address" rows="3" class="form-input" maxlength="255"
                        x-model.trim="form.address" x-bind:class="{
                                'form-input-error':
                                    fieldError('address')
                            }" x-bind:aria-invalid="
                                Boolean(fieldError('address'))
                            "></textarea>

                    <p x-cloak x-show="fieldError('address')" class="mt-1.5 text-sm text-rose-600"
                        x-text="fieldError('address')"></p>
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeFormModal()" x-bind:disabled="submitting">
                Hủy
            </x-ui.button>

            <x-ui.button type="submit" form="patient-form" x-bind:disabled="submitting">
                <span x-show="submitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    aria-hidden="true"></span>

                <span x-text="
                            submitting
                                ? 'Đang lưu…'
                                : 'Lưu bệnh nhân'
                        "></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Delete confirmation popup --}}
    <x-ui.confirm-modal show="deleteTarget" cancel="cancelDelete()" confirm="confirmDelete()" id="patient-delete"
        title="Xóa bệnh nhân?" busy="deleting" confirm-label="Xóa bệnh nhân" busy-label="Đang xóa…">
        <p>
            Hồ sơ
            <strong x-text="deleteTarget?.full_name"></strong>
            sẽ không còn xuất hiện trong danh sách.
        </p>

        <p class="text-xs text-slate-500">
            Dữ liệu được xóa mềm và không bị xóa vật lý khỏi database.
        </p>
    </x-ui.confirm-modal>
</div>
@endsection
