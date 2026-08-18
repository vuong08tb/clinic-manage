export function createUiStore() {
    return {
        sidebarOpen: false,
        toast: null,
        _toastTimer: null,

        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
        },

        closeSidebar() {
            this.sidebarOpen = false;
        },

        notify(message, type = 'info') {
            window.clearTimeout(this._toastTimer);
            this.toast = { message, type };
            this._toastTimer = window.setTimeout(() => {
                this.toast = null;
            }, 5000);
        },

        dismissToast() {
            window.clearTimeout(this._toastTimer);
            this.toast = null;
        },
    };
}
