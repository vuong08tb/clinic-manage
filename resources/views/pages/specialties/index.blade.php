@extends('layouts.app')

@section('title', 'Chuyên khoa')

@section('content')
<div x-data="specialtyIndexPage" x-on:keydown.escape.window="handleEscape()"
    class="mx-auto max-w-[1200px] space-y-6">
    <x-layout.page-header title="Chuyên khoa" description="Danh mục chuyên khoa dùng để gán cho hồ sơ bác sĩ.">
        <x-slot:actions>
            <x-ui.button variant="secondary" x-on:click="refresh()" x-bind:disabled="refreshing">
                <x-ui.icon name="refresh" x-bind:class="{ 'animate-spin': refreshing }" />
                <span x-text="refreshing ? 'Đang tải' : 'Làm mới'"></span>
            </x-ui.button>

            <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">
                <x-ui.icon name="specialty" />
                Thêm chuyên khoa
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <section class="surface-card p-4 sm:p-5">
        <label for="specialty-search" class="mb-2 block text-sm font-semibold text-slate-700">Tìm kiếm</label>
        <input id="specialty-search" type="search" class="form-input" placeholder="Tên hoặc mô tả chuyên khoa"
            maxlength="255" x-model="filters.q" x-on:input.debounce.350ms="search()">
    </section>

    <section x-show="loading" class="surface-card space-y-3 p-6" role="status">
        <template x-for="index in 6" :key="index">
            <div class="h-14 animate-pulse rounded-xl bg-slate-100"></div>
        </template>
    </section>

    <section x-cloak x-show="!loading && listError" class="surface-card p-10 text-center">
        <p class="font-semibold text-rose-700">Không thể tải danh sách chuyên khoa</p>
        <p class="mt-2 text-sm text-slate-500" x-text="listError"></p>
        <x-ui.button variant="secondary" class="mt-5" x-on:click="loadList()">Thử lại</x-ui.button>
    </section>

    <section x-cloak x-show="!loading && !listError && specialties.length === 0" class="surface-card">
        <x-ui.empty-state icon="specialty" title="Không tìm thấy chuyên khoa"
            description="Hãy thử từ khóa khác hoặc thêm chuyên khoa mới.">
            <x-slot:action>
                <x-ui.button x-show="canCreate" x-on:click="openCreateModal()">Thêm chuyên khoa</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    </section>

    <section x-cloak x-show="!loading && !listError && specialties.length > 0" class="surface-card overflow-hidden">
        {{-- Mobile cards --}}
        <div class="divide-y divide-slate-100 md:hidden">
            <template x-for="specialty in specialties" :key="specialty.id">
                <article class="space-y-2 p-4">
                    <h3 class="font-bold text-slate-900" x-text="specialty.name"></h3>
                    <p class="truncate text-sm text-slate-500" x-text="specialty.description || 'Không có mô tả'"></p>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                        <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                            x-on:click="openDetailModal(specialty)" />
                        <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                            x-on:click="openEditModal(specialty)" />
                        <x-ui.row-action label="Xóa" icon="trash" tone="danger" x-show="canDelete"
                            x-on:click="askDelete(specialty)" />
                    </div>
                </article>
            </template>
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        <th class="px-5 py-3">Tên chuyên khoa</th>
                        <th class="px-5 py-3">Mô tả</th>
                        <th class="px-5 py-3">Ngày tạo</th>
                        <th class="px-5 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="specialty in specialties" :key="specialty.id">
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-4 text-sm font-semibold text-slate-900" x-text="specialty.name"></td>
                            <td class="max-w-sm truncate px-5 py-4 text-sm text-slate-600"
                                x-text="specialty.description || '—'"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600"
                                x-text="formatDate(specialty.created_at, { dateStyle: 'medium' })"></td>
                            <td class="whitespace-nowrap px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <x-ui.row-action label="Xem" icon="eye" x-show="canView"
                                        x-on:click="openDetailModal(specialty)" />
                                    <x-ui.row-action label="Sửa" icon="edit" tone="neutral" x-show="canUpdate"
                                        x-on:click="openEditModal(specialty)" />
                                    <x-ui.row-action label="Xóa" icon="trash" tone="danger" x-show="canDelete"
                                        x-on:click="askDelete(specialty)" />
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
                x-text="`Hiển thị ${meta.from ?? 0}–${meta.to ?? 0} trong ${meta.total} chuyên khoa`"></p>
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
    <x-ui.modal show="detailOpen" close="closeDetailModal()" id="specialty-detail" title="Chi tiết chuyên khoa">
        <div x-show="detailLoading" class="space-y-3" role="status">
            <div class="h-5 w-40 animate-pulse rounded bg-slate-100"></div>
            <div class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
        </div>

        <p x-cloak x-show="detailError" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
            role="alert" x-text="detailError"></p>

        <dl x-cloak x-show="!detailLoading && !detailError && detail" class="space-y-5">
            <div>
                <dt class="text-sm font-medium text-slate-500">Tên chuyên khoa</dt>
                <dd class="mt-1 text-lg font-bold text-slate-900" x-text="detail?.name"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Mô tả</dt>
                <dd class="mt-1 whitespace-pre-line text-sm text-slate-700" x-text="detail?.description || 'Không có mô tả.'"></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-slate-500">Ngày tạo</dt>
                <dd class="mt-1 font-semibold text-slate-900" x-text="formatDate(detail?.created_at)"></dd>
            </div>
        </dl>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeDetailModal()">Đóng</x-ui.button>
            <x-ui.button x-show="canUpdate" x-on:click="editFromDetail()">Sửa chuyên khoa</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Create/update modal --}}
    <x-ui.modal show="formOpen" close="closeFormModal()" id="specialty-form-modal"
        title-expr="formMode === 'create' ? 'Thêm chuyên khoa' : 'Cập nhật chuyên khoa'">
        <form id="specialty-form" x-on:submit.prevent="submitForm()" novalidate class="space-y-5">
            <div x-cloak x-show="formMessage"
                class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"
                x-text="formMessage"></div>

            <div>
                <label for="specialty-name" class="mb-2 block text-sm font-semibold text-slate-700">
                    Tên chuyên khoa <span class="text-rose-600">*</span>
                </label>
                <input id="specialty-name" type="text" class="form-input" maxlength="255" required
                    x-model.trim="form.name" x-bind:class="{ 'form-input-error': fieldError('name') }">
                <p x-cloak x-show="fieldError('name')" class="mt-1.5 text-sm text-rose-600" x-text="fieldError('name')"></p>
            </div>

            <div>
                <label for="specialty-description" class="mb-2 block text-sm font-semibold text-slate-700">
                    Mô tả
                </label>
                <textarea id="specialty-description" rows="4" class="form-input" maxlength="2000"
                    x-model.trim="form.description" x-bind:class="{ 'form-input-error': fieldError('description') }"></textarea>
                <p x-cloak x-show="fieldError('description')" class="mt-1.5 text-sm text-rose-600"
                    x-text="fieldError('description')"></p>
            </div>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="closeFormModal()" x-bind:disabled="submitting">Hủy</x-ui.button>
            <x-ui.button type="submit" form="specialty-form" x-bind:disabled="submitting">
                <span x-show="submitting"
                    class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                    aria-hidden="true"></span>
                <span x-text="submitting ? 'Đang lưu…' : 'Lưu chuyên khoa'"></span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Delete confirmation popup --}}
    <x-ui.confirm-modal show="deleteTarget" cancel="cancelDelete()" confirm="confirmDelete()" id="specialty-delete"
        title="Xóa chuyên khoa?" busy="deleting" confirm-label="Xóa chuyên khoa" busy-label="Đang xóa…">
        <p>
            Chuyên khoa <strong x-text="deleteTarget?.name"></strong> sẽ bị xóa khỏi danh mục. Không thể xóa nếu vẫn
            còn bác sĩ thuộc chuyên khoa này.
        </p>
    </x-ui.confirm-modal>
</div>
@endsection
