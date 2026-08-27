import { ApiError } from "../../core/api-error";
import { firstFieldError } from "../../core/form-errors";
import {
    formatCurrency,
    formatDate,
    formatNumber,
    stockClasses,
    stockLabel,
    statusClasses,
    statusLabel,
} from "../../core/formatters";
import {
    calculateVisiblePages,
    emptyPaginationMeta,
} from "../../core/pagination";
import { PERMISSIONS } from "../../core/permissions";
import {
    adjustMedicineStock,
    createMedicine,
    deleteMedicine,
    getMedicine,
    getMedicines,
    updateMedicine,
} from "./medicine-api";
import {
    createPayload,
    emptyMedicineForm,
    emptyStockForm,
    medicineToForm,
    stockAdjustPayload,
    updatePayload,
} from "./medicine-form";

export function medicineIndexPage() {
    return {
        medicines: [],
        meta: emptyPaginationMeta(),

        filters: {
            q: "",
            stock_status: "",
            page: 1,
            per_page: 15,
        },

        loading: true,
        refreshing: false,
        listError: "",
        _listRequestId: 0,

        formOpen: false,
        formMode: "create",
        editingId: null,
        editingCode: null,
        form: emptyMedicineForm(),
        formErrors: {},
        formMessage: "",
        submitting: false,

        detailOpen: false,
        detail: null,
        detailLoading: false,
        detailError: "",

        deleteTarget: null,
        deleting: false,

        // Adjust-stock modal: a separate, delta-based flow so "sửa thuốc" can
        // never silently overwrite stock without a reason (see medicine-form.js).
        stockOpen: false,
        stockTarget: null,
        stockForm: emptyStockForm(),
        stockErrors: {},
        stockMessage: "",
        stockSubmitting: false,

        get canView() {
            return this.$store.auth.can(PERMISSIONS.MEDICINES.FINDONE);
        },

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.MEDICINES.CREATE);
        },

        get canUpdate() {
            return this.$store.auth.can(PERMISSIONS.MEDICINES.UPDATE);
        },

        get canDelete() {
            return this.$store.auth.can(PERMISSIONS.MEDICINES.DELETE);
        },

        get canAdjustStock() {
            return this.$store.auth.can(PERMISSIONS.MEDICINES.ADJUSTSTOCK);
        },

        get visiblePages() {
            return calculateVisiblePages(this.meta);
        },

        get stockResultingAmount() {
            const amount = Math.abs(Number(this.stockForm.amount) || 0);
            const signed =
                this.stockForm.direction === "out" ? -amount : amount;

            return (this.stockTarget?.stock ?? 0) + signed;
        },

        get stockGoesNegative() {
            return this.stockForm.amount !== "" && this.stockResultingAmount < 0;
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.MEDICINES.FINDALL)) {
                this.$store.ui.notify(
                    "Bạn không có quyền xem danh sách thuốc.",
                    "error",
                );

                window.location.replace("/dashboard");

                return;
            }

            await this.loadMedicines();
        },

        async loadMedicines() {
            const requestId = ++this._listRequestId;

            this.loading = true;
            this.listError = "";

            try {
                const response = await getMedicines(this.filters);

                if (requestId !== this._listRequestId) {
                    return;
                }

                this.medicines = response.data ?? [];
                this.meta = {
                    ...emptyPaginationMeta(),
                    ...(response.meta ?? {}),
                };

                this.filters.page = Number(this.meta.current_page ?? 1);
            } catch (error) {
                if (requestId !== this._listRequestId) {
                    return;
                }

                this.medicines = [];

                if (error instanceof ApiError && error.status === 403) {
                    this.listError = "Bạn không có quyền xem danh sách thuốc.";
                } else {
                    this.listError =
                        error.message ?? "Không thể tải danh sách thuốc.";
                }
            } finally {
                if (requestId === this._listRequestId) {
                    this.loading = false;
                    this.refreshing = false;
                }
            }
        },

        async refresh() {
            if (this.refreshing) {
                return;
            }

            this.refreshing = true;

            await this.loadMedicines();
        },

        async search() {
            this.filters.page = 1;

            await this.loadMedicines();
        },

        async applyFilters() {
            this.filters.page = 1;

            await this.loadMedicines();
        },

        async changePerPage() {
            this.filters.page = 1;

            await this.loadMedicines();
        },

        async goToPage(page) {
            const targetPage = Number(page);

            if (
                targetPage < 1 ||
                targetPage > this.meta.last_page ||
                targetPage === this.meta.current_page
            ) {
                return;
            }

            this.filters.page = targetPage;

            await this.loadMedicines();
        },

        // --- Create/edit form ---

        resetForm() {
            this.form = emptyMedicineForm();
            this.formErrors = {};
            this.formMessage = "";
            this.editingId = null;
            this.editingCode = null;
        },

        openCreateModal() {
            if (!this.canCreate) {
                return;
            }

            this.resetForm();
            this.formMode = "create";
            this.formOpen = true;
        },

        openEditModal(medicine) {
            if (!this.canUpdate) {
                return;
            }

            this.resetForm();

            this.formMode = "edit";
            this.editingId = medicine.id;
            this.editingCode = medicine.code;
            this.form = medicineToForm(medicine);

            this.formOpen = true;
        },

        closeFormModal() {
            if (this.submitting) {
                return;
            }

            this.formOpen = false;
            this.resetForm();
        },

        async submitForm() {
            if (this.submitting) {
                return;
            }

            const editing = this.formMode === "edit";
            const allowed = editing ? this.canUpdate : this.canCreate;

            if (!allowed) {
                this.$store.ui.notify(
                    "Bạn không có quyền thực hiện thao tác này.",
                    "error",
                );

                return;
            }

            this.submitting = true;
            this.formErrors = {};
            this.formMessage = "";

            try {
                if (editing) {
                    await updateMedicine(this.editingId, updatePayload(this.form));
                } else {
                    await createMedicine(createPayload(this.form));
                }

                this.$store.ui.notify(
                    editing
                        ? "Cập nhật thuốc thành công."
                        : "Thêm thuốc thành công.",
                    "success",
                );

                this.formOpen = false;
                this.resetForm();

                if (!editing) {
                    this.filters.page = 1;
                }

                await this.loadMedicines();
            } catch (error) {
                if (error instanceof ApiError) {
                    this.formErrors = error.errors ?? {};
                    this.formMessage =
                        error.status === 403
                            ? "Bạn không có quyền thực hiện thao tác này."
                            : error.message;
                } else {
                    this.formMessage =
                        "Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.";
                }
            } finally {
                this.submitting = false;
            }
        },

        fieldError(field) {
            return firstFieldError(this.formErrors, field);
        },

        // --- Detail modal ---

        async openDetailModal(medicine) {
            if (!this.canView) {
                return;
            }

            this.detailOpen = true;
            this.detailError = "";
            this.detailLoading = true;
            this.detail = medicine;

            try {
                const response = await getMedicine(medicine.id);

                this.detail = response.data;
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    this.detailError = "Không tìm thấy thuốc.";
                } else if (error instanceof ApiError && error.status === 403) {
                    this.detailError = "Bạn không có quyền xem thuốc này.";
                } else {
                    this.detailError =
                        error.message ?? "Không thể tải thông tin thuốc.";
                }
            } finally {
                this.detailLoading = false;
            }
        },

        closeDetailModal() {
            this.detailOpen = false;
            this.detail = null;
            this.detailError = "";
        },

        editFromDetail() {
            const medicine = this.detail;

            if (!medicine) {
                return;
            }

            this.closeDetailModal();
            this.openEditModal(medicine);
        },

        // --- Adjust stock modal ---

        askAdjustStock(medicine) {
            if (!this.canAdjustStock) {
                return;
            }

            this.stockTarget = medicine;
            this.stockForm = emptyStockForm();
            this.stockErrors = {};
            this.stockMessage = "";
            this.stockOpen = true;
        },

        closeStockModal() {
            if (this.stockSubmitting) {
                return;
            }

            this.stockOpen = false;
            this.stockTarget = null;
            this.stockForm = emptyStockForm();
            this.stockErrors = {};
            this.stockMessage = "";
        },

        async submitStockAdjust() {
            if (this.stockSubmitting || !this.canAdjustStock || !this.stockTarget) {
                return;
            }

            const adjustedMedicineId = this.stockTarget.id;

            if (!(Number(this.stockForm.amount) > 0)) {
                this.stockMessage = "Vui lòng nhập số lượng lớn hơn 0.";

                return;
            }

            if (this.stockGoesNegative) {
                this.stockMessage = "Số lượng xuất vượt quá tồn kho hiện có.";

                return;
            }

            this.stockSubmitting = true;
            this.stockErrors = {};
            this.stockMessage = "";
            let adjusted = false;

            try {
                await adjustMedicineStock(
                    this.stockTarget.id,
                    stockAdjustPayload(this.stockForm),
                );

                adjusted = true;

                this.$store.ui.notify(
                    "Điều chỉnh tồn kho thành công.",
                    "success",
                );
            } catch (error) {
                if (error instanceof ApiError) {
                    this.stockErrors = error.errors ?? {};
                    this.stockMessage =
                        error.status === 403
                            ? "Bạn không có quyền thực hiện thao tác này."
                            : error.message;
                } else {
                    this.stockMessage =
                        "Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.";
                }
            } finally {
                this.stockSubmitting = false;
            }

            if (!adjusted) {
                return;
            }

            // Closing is blocked while a request is in flight. Run it only
            // after the busy state has been released so the old amount cannot
            // remain in an open form and be submitted a second time.
            this.closeStockModal();

            if (this.detailOpen && this.detail?.id === adjustedMedicineId) {
                await this.openDetailModal({ id: adjustedMedicineId });
            }

            await this.loadMedicines();
        },

        stockFieldError(field) {
            return firstFieldError(this.stockErrors, field);
        },

        // --- Delete confirm ---

        askDelete(medicine) {
            if (!this.canDelete) {
                return;
            }

            this.deleteTarget = medicine;
        },

        cancelDelete() {
            if (this.deleting) {
                return;
            }

            this.deleteTarget = null;
        },

        async confirmDelete() {
            if (!this.deleteTarget || this.deleting || !this.canDelete) {
                return;
            }

            this.deleting = true;

            try {
                await deleteMedicine(this.deleteTarget.id);

                this.$store.ui.notify("Xóa thuốc thành công.", "success");

                this.deleteTarget = null;

                if (this.detailOpen) {
                    this.closeDetailModal();
                }

                if (this.medicines.length === 1 && this.filters.page > 1) {
                    this.filters.page -= 1;
                }

                await this.loadMedicines();
            } catch (error) {
                this.$store.ui.notify(
                    error.message ?? "Không thể xóa thuốc.",
                    "error",
                );
            } finally {
                this.deleting = false;
            }
        },

        handleEscape() {
            if (this.deleteTarget) {
                this.cancelDelete();

                return;
            }

            if (this.stockOpen) {
                this.closeStockModal();

                return;
            }

            if (this.formOpen) {
                this.closeFormModal();

                return;
            }

            if (this.detailOpen) {
                this.closeDetailModal();
            }
        },

        formatDate,
        formatNumber,
        formatCurrency,
        statusLabel,
        statusClasses,
        stockLabel,
        stockClasses,
    };
}
