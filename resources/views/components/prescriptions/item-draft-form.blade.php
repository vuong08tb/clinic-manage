{{-- Add-medicine-to-prescription form shared by the create modal (pushes to a local
     draft list) and the detail modal (calls the addItem API). It reads the parent
     page's Alpine state directly, so it takes no props. --}}
<div class="grid gap-3 sm:grid-cols-4">
    <div class="relative sm:col-span-2">
        <label class="mb-1 block text-xs font-semibold text-slate-600">Thuốc</label>

        <div x-show="itemDraft.medicine_id"
            class="flex items-center justify-between rounded-xl border border-slate-300 px-3 py-2">
            <div>
                <span class="text-sm text-slate-800" x-text="itemDraft.medicine_label"></span>
                <span class="ml-1 text-xs text-slate-500" x-text="`· Tồn: ${itemDraft.stock} ${itemDraft.unit}`"></span>
            </div>
            <button type="button" class="text-xs font-semibold text-rose-600" x-on:click="clearMedicineDraft()">
                Đổi
            </button>
        </div>

        <div x-show="!itemDraft.medicine_id">
            <input type="text" class="form-input" placeholder="Tìm theo tên hoặc mã thuốc" x-model="medicineQuery"
                x-on:input.debounce.350ms="searchMedicines()">

            <ul x-show="medicineResults.length > 0"
                class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                <template x-for="medicine in medicineResults" :key="medicine.id">
                    <li>
                        <button type="button" x-on:click="selectMedicineForDraft(medicine)"
                            class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50">
                            <span class="font-semibold" x-text="medicine.name"></span>
                            <span class="ml-1 text-xs text-slate-500"
                                x-text="`(${medicine.code}) · Tồn: ${medicine.stock} · ${formatCurrency(medicine.price)}`"></span>
                        </button>
                    </li>
                </template>
            </ul>

            <p x-show="!searchingMedicine && medicineQuery.trim() !== '' && medicineResults.length === 0"
                class="mt-2 text-xs text-slate-400">
                Không tìm thấy thuốc còn hàng phù hợp.
            </p>
        </div>
    </div>

    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">Số lượng</label>
        <input type="number" min="1" class="form-input" x-model.number="itemDraft.quantity"
            x-bind:class="{ 'form-input-error': draftExceedsStock }">
        <p x-cloak x-show="draftExceedsStock" class="mt-1 text-xs text-rose-600"
            x-text="`Vượt tồn kho (còn ${itemDraft.stock})`"></p>
    </div>

    <div>
        <label class="mb-1 block text-xs font-semibold text-slate-600">Liều dùng</label>
        <input type="text" class="form-input" x-model.trim="itemDraft.dosage">
    </div>

    <div class="sm:col-span-3">
        <label class="mb-1 block text-xs font-semibold text-slate-600">Cách dùng</label>
        <input type="text" class="form-input" x-model.trim="itemDraft.usage_instruction">
    </div>

    <div class="flex items-end">
        <x-ui.button type="button" variant="secondary" x-on:click="addItemFromDraft()" x-bind:disabled="addingItem"
            class="w-full">
            <x-ui.icon name="plus" size="h-4 w-4" />
            <span x-text="addingItem ? 'Đang thêm…' : 'Thêm vào toa'"></span>
        </x-ui.button>
    </div>
</div>

<p x-cloak x-show="itemDraftError" class="text-sm text-rose-600" x-text="itemDraftError"></p>
