import { ApiError } from "../../core/api-error";
import { firstFieldError } from "../../core/form-errors";
import { formatDate } from "../../core/formatters";
import {
    calculateVisiblePages,
    emptyPaginationMeta,
} from "../../core/pagination";
import { PERMISSIONS } from "../../core/permissions";
import {
    createDoctor,
    deleteDoctor,
    getDoctor,
    getDoctors,
    listSpecialtyOptions,
    searchDoctorEligibleUsers,
    updateDoctor,
} from "./doctor-api";
import { doctorToForm, emptyDoctorForm, formToPayload } from "./doctor-form";

export function doctorIndexPage() {
    return {
        doctors: [],
        meta: emptyPaginationMeta(),

        filters: {
            q: "",
            specialty_id: "",
            page: 1,
            per_page: 15,
        },

        specialtyOptions: [],

        loading: true,
        refreshing: false,
        listError: "",
        _listRequestId: 0,

        formOpen: false,
        formMode: "create",
        editingId: null,
        form: emptyDoctorForm(),
        formErrors: {},
        formMessage: "",
        submitting: false,

        userQuery: "",
        userResults: [],
        searchingUser: false,
        _userSearchRequestId: 0,

        detailOpen: false,
        detail: null,
        detailLoading: false,
        detailError: "",

        deleteTarget: null,
        deleting: false,

        get canView() {
            return this.$store.auth.can(PERMISSIONS.DOCTORS.FINDONE);
        },

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.DOCTORS.CREATE);
        },

        get canUpdate() {
            return this.$store.auth.can(PERMISSIONS.DOCTORS.UPDATE);
        },

        get canDelete() {
            return this.$store.auth.can(PERMISSIONS.DOCTORS.DELETE);
        },

        get visiblePages() {
            return calculateVisiblePages(this.meta);
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.DOCTORS.FINDALL)) {
                this.$store.ui.notify(
                    "Bạn không có quyền xem danh sách bác sĩ.",
                    "error",
                );

                window.location.replace("/dashboard");

                return;
            }

            await Promise.all([this.loadSpecialtyOptions(), this.loadList()]);
        },

        async loadSpecialtyOptions() {
            try {
                const response = await listSpecialtyOptions();

                this.specialtyOptions = response.data ?? [];
            } catch {
                this.specialtyOptions = [];
            }
        },

        async loadList() {
            const requestId = ++this._listRequestId;

            this.loading = true;
            this.listError = "";

            try {
                const response = await getDoctors(this.filters);

                if (requestId !== this._listRequestId) {
                    return;
                }

                this.doctors = response.data ?? [];
                this.meta = {
                    ...emptyPaginationMeta(),
                    ...(response.meta ?? {}),
                };

                this.filters.page = Number(this.meta.current_page ?? 1);
            } catch (error) {
                if (requestId !== this._listRequestId) {
                    return;
                }

                this.doctors = [];

                if (error instanceof ApiError && error.status === 403) {
                    this.listError = "Bạn không có quyền xem danh sách bác sĩ.";
                } else {
                    this.listError =
                        error.message ?? "Không thể tải danh sách bác sĩ.";
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

        // --- User picker (create/edit form) ---

        async searchUsers() {
            const requestId = ++this._userSearchRequestId;
            const term = this.userQuery.trim();

            if (term === "") {
                this.userResults = [];

                return;
            }

            this.searchingUser = true;

            try {
                const response = await searchDoctorEligibleUsers(term);

                if (requestId !== this._userSearchRequestId) {
                    return;
                }

                this.userResults = response.data ?? [];
            } catch {
                if (requestId === this._userSearchRequestId) {
                    this.userResults = [];
                }
            } finally {
                if (requestId === this._userSearchRequestId) {
                    this.searchingUser = false;
                }
            }
        },

        selectUser(user) {
            this.form.user_id = user.id;
            this.form.user_label = `${user.name} (${user.email})`;
            this.userQuery = "";
            this.userResults = [];
        },

        clearUser() {
            this.form.user_id = null;
            this.form.user_label = "";
        },

        // --- Create/edit form ---

        resetForm() {
            this.form = emptyDoctorForm();
            this.formErrors = {};
            this.formMessage = "";
            this.editingId = null;
            this.userQuery = "";
            this.userResults = [];
        },

        openCreateModal() {
            if (!this.canCreate) {
                return;
            }

            this.resetForm();
            this.formMode = "create";
            this.formOpen = true;
        },

        openEditModal(doctor) {
            if (!this.canUpdate) {
                return;
            }

            this.resetForm();

            this.formMode = "edit";
            this.editingId = doctor.id;
            this.form = doctorToForm(doctor);
            this.formOpen = true;
        },

        closeFormModal() {
            if (this.submitting) {
                return;
            }

            this.formOpen = false;
            this.resetForm();
        },

        async openDetailModal(doctor) {
            if (!this.canView) {
                return;
            }

            this.detailOpen = true;
            this.detailError = "";
            this.detailLoading = true;
            this.detail = doctor;

            try {
                const response = await getDoctor(doctor.id);

                this.detail = response.data;
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    this.detailError = "Không tìm thấy bác sĩ.";
                } else if (error instanceof ApiError && error.status === 403) {
                    this.detailError = "Bạn không có quyền xem bác sĩ này.";
                } else {
                    this.detailError = error.message ?? "Không thể tải hồ sơ bác sĩ.";
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
            const doctor = this.detail;

            if (!doctor) {
                return;
            }

            this.closeDetailModal();
            this.openEditModal(doctor);
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

            if (!this.form.user_id) {
                this.formMessage = "Vui lòng chọn tài khoản bác sĩ.";

                return;
            }

            if (!this.form.specialty_id) {
                this.formMessage = "Vui lòng chọn chuyên khoa.";

                return;
            }

            this.submitting = true;
            this.formErrors = {};
            this.formMessage = "";

            try {
                const payload = formToPayload(this.form);

                if (editing) {
                    await updateDoctor(this.editingId, payload);
                } else {
                    await createDoctor(payload);
                }

                this.$store.ui.notify(
                    editing
                        ? "Cập nhật bác sĩ thành công."
                        : "Thêm bác sĩ thành công.",
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
                            : (firstFieldError(error.errors, "doctor") ??
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

        askDelete(doctor) {
            if (!this.canDelete) {
                return;
            }

            this.deleteTarget = doctor;
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
                await deleteDoctor(this.deleteTarget.id);

                this.$store.ui.notify("Xóa bác sĩ thành công.", "success");

                this.deleteTarget = null;

                if (this.detailOpen) {
                    this.closeDetailModal();
                }

                if (this.doctors.length === 1 && this.filters.page > 1) {
                    this.filters.page -= 1;
                }

                await this.loadList();
            } catch (error) {
                const message =
                    error instanceof ApiError
                        ? (firstFieldError(error.errors, "doctor") ?? error.message)
                        : "Không thể xóa bác sĩ.";

                this.$store.ui.notify(message, "error");
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
