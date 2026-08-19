<div
    x-cloak
    x-show="$store.ui.toast"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="translate-y-2 opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-2 opacity-0"
    class="fixed right-4 bottom-4 z-50 flex max-w-sm items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-xl"
    role="status"
>
    <span
        class="mt-0.5 h-2.5 w-2.5 rounded-full"
        :class="{
            'bg-blue-500': $store.ui.toast?.type === 'info',
            'bg-emerald-500': $store.ui.toast?.type === 'success',
            'bg-rose-500': $store.ui.toast?.type === 'error'
        }"
    ></span>
    <p class="text-sm text-slate-700" x-text="$store.ui.toast?.message"></p>
    <button type="button" class="ml-auto text-slate-400 hover:text-slate-700" x-on:click="$store.ui.dismissToast()" aria-label="Đóng thông báo">
        <x-ui.icon name="close" size="h-4 w-4" />
    </button>
</div>
