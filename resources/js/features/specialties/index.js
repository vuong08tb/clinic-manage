import { ApiError } from "../../core/api-error";
import { firstFieldError } from "../../core/form-errors";
import { formatDate } from "../../core/formatters";
import {
    calculateVisiblePages,
    emptyPaginationMeta,
} from "../../core/pagination";
import { PERMISSIONS } from "../../core/permissions";
import {
    createSpecialty,
    deleteSpecialty,
    getSpecialties,
    getSpecialty,
    updateSpecialty,
} from "./specialty-api";
import {
    emptySpecialtyForm,
    formToPayload,
    specialtyToForm,
} from "./specialty-form";

export function specialtyIndexPage() {
    return {
        specialties: [],
        meta: emptyPaginationMeta(),

        filters: {
            q: "",
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
        form: emptySpecialtyForm(),
        formErrors: {},
        formMessage: "",
        submitting: false,

        detailOpen: false,
        detail: null,
        detailLoading: false,
        detailError: "",

        deleteTarget: null,
        deleting: false,

        get canView() {
            return this.$store.auth.can(PERMISSIONS.SPECIALTIES.FINDONE);
        },

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.SPECIALTIES.CREATE);
        },

        get canUpdate() {
            return this.$store.auth.can(PERMISSIONS.SPECIALTIES.UPDATE);
        },

        get canDelete() {
            return this.$store.auth.can(PERMISSIONS.SPECIALTIES.DELETE);
        },

        get visiblePages() {
            return calculateVisiblePages(this.meta);
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.SPECIALTIES.FINDALL)) {
                this.$store.ui.notify(
                    "Bạn không có quyền xem danh sách chuyên khoa.",
                    "error",
                );

                window.location.replace("/dashboard");

                return;
            }

            await this.loadList();
        },

        async loadList() {
            const requestId = ++this._listRequestId;

            this.loading = true;
            this.listError = "";

            try {
                const response = await getSpecialties(this.filters);

                if (requestId !== this._listRequestId) {
                    return;
                }

                this.specialties = response.data ?? [];
                this.meta = {
                    ...emptyPaginationMeta(),
                    ...(response.meta ?? {}),
                };

                this.filters.page = Number(this.meta.current_page ?? 1);
            } catch (error) {
                if (requestId !== this._listRequestId) {
                    return;
                }

                this.specialties = [];

                if (error instanceof ApiError && error.status === 403) {
                    this.listError = "Bạn không có quyền xem danh sách chuyên khoa.";
                } else {
                    this.listError =
                        error.message ?? "Không thể tải danh sách chuyên khoa.";
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

            await this.loadList();
        },

        async search() {
            this.filters.page = 1;

            await this.loadList();
        },

        async changePerPage() {
            this.filters.page = 1;

            await this.loadList();
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

            await this.loadList();
        },

        resetForm() {
            this.form = emptySpecialtyForm();
            this.formErrors = {};
            this.formMessage = "";
            this.editingId = null;
        },

        openCreateModal() {
            if (!this.canCreate) {
                return;
            }

            this.resetForm();
            this.formMode = "create";
            this.formOpen = true;
        },

        openEditModal(specialty) {
            if (!this.canUpdate) {
                return;
            }

            this.resetForm();

            this.formMode = "edit";
            this.editingId = specialty.id;
            this.form = specialtyToForm(specialty);
            this.formOpen = true;
        },

        closeFormModal() {
            if (this.submitting) {
                return;
            }

            this.formOpen = false;
            this.resetForm();
        },

        async openDetailModal(specialty) {
            if (!this.canView) {
                return;
            }

            this.detailOpen = true;
            this.detailError = "";
            this.detailLoading = true;
            this.detail = specialty;

            try {
                const response = await getSpecialty(specialty.id);

                this.detail = response.data;
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    this.detailError = "Không tìm thấy chuyên khoa.";
                } else if (error instanceof ApiError && error.status === 403) {
                    this.detailError = "Bạn không có quyền xem chuyên khoa này.";
                } else {
                    this.detailError =
                        error.message ?? "Không thể tải chuyên khoa.";
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
            const specialty = this.detail;

            if (!specialty) {
                return;
            }

            this.closeDetailModal();
            this.openEditModal(specialty);
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
                const payload = formToPayload(this.form);

                if (editing) {
                    await updateSpecialty(this.editingId, payload);
                } else {
                    await createSpecialty(payload);
                }

                this.$store.ui.notify(
                    editing
                        ? "Cập nhật chuyên khoa thành công."
                        : "Thêm chuyên khoa thành công.",
                    "success",
                );

                this.formOpen = false;
                this.resetForm();

                if (!editing) {
                    this.filters.page = 1;
                }

                await this.loadList();
            } catch (error) {
                if (error instanceof ApiError) {
                    this.formErrors = error.errors ?? {};
                    this.formMessage =
                        error.status === 403
                            ? "Bạn không có quyền thực hiện thao tác này."
                            : (firstFieldError(error.errors, "specialty") ??
                                error.message);
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

        askDelete(specialty) {
            if (!this.canDelete) {
                return;
            }

            this.deleteTarget = specialty;
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
                await deleteSpecialty(this.deleteTarget.id);

                this.$store.ui.notify("Xóa chuyên khoa thành công.", "success");

                this.deleteTarget = null;

                if (this.detailOpen) {
                    this.closeDetailModal();
                }

                if (this.specialties.length === 1 && this.filters.page > 1) {
                    this.filters.page -= 1;
                }

                await this.loadList();
            } catch (error) {
                this.$store.ui.notify(
                    error instanceof ApiError
                        ? error.message
                        : "Không thể xóa chuyên khoa.",
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

            if (this.formOpen) {
                this.closeFormModal();

                return;
            }

            if (this.detailOpen) {
                this.closeDetailModal();
            }
        },

        formatDate,
    };
}
