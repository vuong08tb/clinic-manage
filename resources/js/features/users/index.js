import { ApiError } from "../../core/api-error";
import { firstFieldError } from "../../core/form-errors";
import {
    formatDate,
    statusClasses,
    statusLabel,
} from "../../core/formatters";
import {
    calculateVisiblePages,
    emptyPaginationMeta,
} from "../../core/pagination";
import { PERMISSIONS } from "../../core/permissions";
import {
    createUser,
    getUser,
    getUsers,
    listRoleOptions,
    updateUser,
    updateUserStatus,
} from "./user-api";
import { createPayload, editPayload, emptyUserForm, userToForm } from "./user-form";

export function userIndexPage() {
    return {
        users: [],
        meta: emptyPaginationMeta(),

        filters: {
            q: "",
            role_id: "",
            is_active: "",
            page: 1,
            per_page: 15,
        },

        roleOptions: [],

        loading: true,
        refreshing: false,
        listError: "",
        _listRequestId: 0,

        formOpen: false,
        formMode: "create",
        editingId: null,
        form: emptyUserForm(),
        formErrors: {},
        formMessage: "",
        submitting: false,

        detailOpen: false,
        detail: null,
        detailLoading: false,
        detailError: "",

        statusTarget: null,
        statusBusy: false,

        get canView() {
            return this.$store.auth.can(PERMISSIONS.USERS.FINDONE);
        },

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.USERS.CREATE);
        },

        get canUpdate() {
            return this.$store.auth.can(PERMISSIONS.USERS.UPDATE);
        },

        get canUpdateStatus() {
            return this.$store.auth.can(PERMISSIONS.USERS.UPDATESTATUS);
        },

        get visiblePages() {
            return calculateVisiblePages(this.meta);
        },

        get isSelf() {
            return (target) => this.$store.auth.user?.id === target?.id;
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.USERS.FINDALL)) {
                this.$store.ui.notify(
                    "Bạn không có quyền xem danh sách người dùng.",
                    "error",
                );

                window.location.replace("/dashboard");

                return;
            }

            await Promise.all([this.loadRoleOptions(), this.loadList()]);
        },

        async loadRoleOptions() {
            try {
                const response = await listRoleOptions();

                this.roleOptions = response.data ?? [];
            } catch {
                this.roleOptions = [];
            }
        },

        async loadList() {
            const requestId = ++this._listRequestId;

            this.loading = true;
            this.listError = "";

            try {
                const response = await getUsers(this.filters);

                if (requestId !== this._listRequestId) {
                    return;
                }

                this.users = response.data ?? [];
                this.meta = {
                    ...emptyPaginationMeta(),
                    ...(response.meta ?? {}),
                };

                this.filters.page = Number(this.meta.current_page ?? 1);
            } catch (error) {
                if (requestId !== this._listRequestId) {
                    return;
                }

                this.users = [];

                if (error instanceof ApiError && error.status === 403) {
                    this.listError = "Bạn không có quyền xem danh sách người dùng.";
                } else {
                    this.listError =
                        error.message ?? "Không thể tải danh sách người dùng.";
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

        async applyFilters() {
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

        // --- Create/edit form ---

        resetForm() {
            this.form = emptyUserForm();
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

        openEditModal(user) {
            if (!this.canUpdate) {
                return;
            }

            this.resetForm();

            this.formMode = "edit";
            this.editingId = user.id;
            this.form = userToForm(user);
            this.formOpen = true;
        },

        closeFormModal() {
            if (this.submitting) {
                return;
            }

            this.formOpen = false;
            this.resetForm();
        },

        async openDetailModal(user) {
            if (!this.canView) {
                return;
            }

            this.detailOpen = true;
            this.detailError = "";
            this.detailLoading = true;
            this.detail = user;

            try {
                const response = await getUser(user.id);

                this.detail = response.data;
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    this.detailError = "Không tìm thấy người dùng.";
                } else if (error instanceof ApiError && error.status === 403) {
                    this.detailError = "Bạn không có quyền xem người dùng này.";
                } else {
                    this.detailError = error.message ?? "Không thể tải hồ sơ người dùng.";
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
            const user = this.detail;

            if (!user) {
                return;
            }

            this.closeDetailModal();
            this.openEditModal(user);
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

            if (!editing || this.form.changePassword) {
                if (this.form.password.length < 8) {
                    this.formMessage = "Mật khẩu phải có ít nhất 8 ký tự.";

                    return;
                }

                if (this.form.password !== this.form.password_confirmation) {
                    this.formMessage = "Xác nhận mật khẩu không khớp.";

                    return;
                }
            }

            this.submitting = true;
            this.formErrors = {};
            this.formMessage = "";

            try {
                if (editing) {
                    await updateUser(this.editingId, editPayload(this.form));
                } else {
                    await createUser(createPayload(this.form));
                }

                this.$store.ui.notify(
                    editing
                        ? "Cập nhật người dùng thành công."
                        : "Thêm người dùng thành công.",
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
                            : (firstFieldError(error.errors, "user") ??
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

        // --- Lock / unlock confirm ---

        askToggleStatus(user) {
            if (!this.canUpdateStatus) {
                return;
            }

            this.statusTarget = user;
        },

        cancelToggleStatus() {
            if (this.statusBusy) {
                return;
            }

            this.statusTarget = null;
        },

        async confirmToggleStatus() {
            if (!this.statusTarget || this.statusBusy || !this.canUpdateStatus) {
                return;
            }

            const target = this.statusTarget;
            const nextActive = !target.is_active;

            this.statusBusy = true;

            try {
                await updateUserStatus(target.id, nextActive);

                this.$store.ui.notify(
                    nextActive
                        ? "Mở khóa tài khoản thành công."
                        : "Khóa tài khoản thành công.",
                    "success",
                );

                this.statusTarget = null;

                if (this.detailOpen && this.detail?.id === target.id) {
                    await this.openDetailModal({ id: target.id });
                }

                await this.loadList();
            } catch (error) {
                const message =
                    error instanceof ApiError
                        ? (firstFieldError(error.errors, "is_active") ??
                            error.message)
                        : "Không thể cập nhật trạng thái tài khoản.";

                this.$store.ui.notify(message, "error");
            } finally {
                this.statusBusy = false;
            }
        },

        handleEscape() {
            if (this.statusTarget) {
                this.cancelToggleStatus();

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
        statusLabel,
        statusClasses,
    };
}
