@extends('layouts.app')

@section('title', 'Chi tiết phiếu khám')

@section('content')
<div x-data="examinationShowPage({{ (int) request()->route('examination') }})" x-init="init()"
    class="mx-auto max-w-3xl space-y-6">
    <x-layout.page-header title="Chi tiết phiếu khám" description="Thông tin khám bệnh và chẩn đoán.">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('web.examinations.index') }}">
                Quay lại danh sách
            </x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <section x-show="loading" class="surface-card space-y-4 p-6" role="status">
        <div class="h-5 w-28 animate-pulse rounded bg-slate-100"></div>
        <div class="h-24 animate-pulse rounded-xl bg-slate-100"></div>
    </section>

    <section x-cloak x-show="!loading && error" class="surface-card p-10 text-center">
        <p class="font-semibold text-rose-700" x-text="error"></p>
        <div class="mt-5 flex justify-center gap-3">
            <x-ui.button variant="secondary" x-on:click="load()">Thử lại</x-ui.button>
            <x-ui.button href="{{ route('web.examinations.index') }}">Về danh sách</x-ui.button>
        </div>
    </section>

    <template x-if="!loading && !error && examination">
        <div class="space-y-6">
            <section class="surface-card overflow-hidden">
                <div class="border-b border-slate-200 bg-gradient-to-r from-blue-700 to-blue-600 p-6 text-white">
                    <p class="text-sm font-semibold text-blue-100" x-text="formatDate(examination.examined_at)"></p>
                    <h2 class="mt-2 text-xl font-bold" x-text="examination.patient?.full_name"></h2>
                    <p class="mt-1 text-sm text-blue-100"
                        x-text="`Bác sĩ khám: ${examination.doctor?.user?.name ?? '—'}`"></p>
                </div>

                <dl class="grid gap-6 p-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Mã bệnh nhân</dt>
                        <dd class="mt-1 font-semibold text-slate-900" x-text="examination.patient?.code"></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Lịch hẹn liên quan</dt>
                        <dd class="mt-1 font-semibold text-slate-900" x-text="`#${examination.appointment_id}`"></dd>
                    </div>
                </dl>
            </section>

            {{-- Read-only view when user cannot update --}}
            <section x-show="!form" class="surface-card space-y-5 p-6">
                <div>
                    <h3 class="text-sm font-semibold text-slate-700">Chẩn đoán</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-800" x-text="examination.diagnosis"></p>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-700">Ghi chú</h3>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-600"
                        x-text="examination.notes || 'Không có ghi chú.'"></p>
                </div>
            </section>

            {{-- Editable form when user has EXAMINATIONS.UPDATE --}}
            <section x-show="form" class="surface-card p-6">
                <form x-on:submit.prevent="submit()" novalidate class="space-y-5">
                    <div x-cloak x-show="message"
                        class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700" role="alert"
                        x-text="message"></div>

                    <div>
                        <label for="edit-diagnosis" class="mb-2 block text-sm font-semibold text-slate-700">
                            Chẩn đoán <span class="text-rose-600">*</span>
                        </label>
                        <textarea id="edit-diagnosis" rows="4" class="form-input" required x-model.trim="form.diagnosis"
                            x-bind:class="{ 'form-input-error': fieldError('diagnosis') }"></textarea>
                        <p x-cloak x-show="fieldError('diagnosis')" class="mt-1.5 text-sm text-rose-600"
                            x-text="fieldError('diagnosis')"></p>
                    </div>

                    <div>
                        <label for="edit-notes" class="mb-2 block text-sm font-semibold text-slate-700">
                            Ghi chú
                        </label>
                        <textarea id="edit-notes" rows="3" class="form-input" x-model.trim="form.notes"
                            x-bind:class="{ 'form-input-error': fieldError('notes') }"></textarea>
                        <p x-cloak x-show="fieldError('notes')" class="mt-1.5 text-sm text-rose-600"
                            x-text="fieldError('notes')"></p>
                    </div>

                    <div class="flex justify-end border-t border-slate-200 pt-5">
                        <x-ui.button type="submit" x-bind:disabled="submitting">
                            <span x-text="submitting ? 'Đang lưu…' : 'Lưu thay đổi'"></span>
                        </x-ui.button>
                    </div>
                </form>
            </section>
        </div>
    </template>
</div>
@endsection