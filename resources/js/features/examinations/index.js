import { ApiError } from "../../core/api-error";
import { firstFieldError } from "../../core/form-errors";
import { formatDate, formatTime } from "../../core/formatters";
import {
    calculateVisiblePages,
    emptyPaginationMeta,
} from "../../core/pagination";
import { PERMISSIONS } from "../../core/permissions";
import {
    createExamination,
    getAppointment,
    getExamination,
    getExaminations,
    listDoctorOptions,
    searchConfirmedAppointments,
    searchPatientOptions,
    updateExamination,
} from "./examination-api";
import {
    createPayload,
    editPayload,
    emptyExaminationForm,
    examinationToForm,
} from "./examination-form";

export function examinationIndexPage() {
    return {
        examinations: [],
        meta: emptyPaginationMeta(),
        loading: true,
        refreshing: false,
        listError: "",
        _listRequestId: 0,

        filters: {
            doctor_id: "",
            patient_id: "",
            patient_label: "",
            page: 1,
            per_page: 15,
        },

        doctorOptions: [],

        patientQuery: "",
        patientResults: [],
        _patientSearchRequestId: 0,

        formOpen: false,
        formMode: "create",
        editingId: null,
        form: emptyExaminationForm(),
        formErrors: {},
        formMessage: "",
        submitting: false,

        appointmentQuery: "",
        appointmentResults: [],
        searchingAppointment: false,
        preselecting: false,
        _appointmentSearchRequestId: 0,

        detailOpen: false,
        detail: null,
        detailLoading: false,
        detailError: "",

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.EXAMINATIONS.CREATE);
        },

        get canView() {
            return this.$store.auth.can(PERMISSIONS.EXAMINATIONS.FINDONE);
        },

        get canUpdate() {
            return this.$store.auth.can(PERMISSIONS.EXAMINATIONS.UPDATE);
        },

        get visiblePages() {
            return calculateVisiblePages(this.meta);
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.EXAMINATIONS.FINDALL)) {
                this.$store.ui.notify(
                    "Bạn không có quyền xem danh sách phiếu khám.",
                    "error",
                );

                window.location.replace("/dashboard");

                return;
            }

            await Promise.all([this.loadDoctorOptions(), this.loadList()]);

            await this.openCreateFromQueryString();
        },

        // Deep link from the appointment list: /examinations?appointment_id=123
        async openCreateFromQueryString() {
            const appointmentId = new URLSearchParams(
                window.location.search,
            ).get("appointment_id");

            if (!appointmentId) {
                return;
            }

            window.history.replaceState({}, "", "/examinations");

            if (!this.canCreate) {
                return;
            }

            this.openCreateModal();

            await this.preselectAppointment(Number(appointmentId));
        },

        async loadDoctorOptions() {
            try {
                const response = await listDoctorOptions();

                this.doctorOptions = (response.data ?? []).map((doctor) => ({
                    id: doctor.id,
                    label: doctor.user?.name ?? `Bác sĩ #${doctor.id}`,
                }));
            } catch {
                this.doctorOptions = [];
            }
        },

        async loadList() {
            const requestId = ++this._listRequestId;

            this.loading = true;
            this.listError = "";

            try {
                const response = await getExaminations(this.filters);

                if (requestId !== this._listRequestId) {
                    return;
                }

                this.examinations = response.data ?? [];
                this.meta = {
                    ...emptyPaginationMeta(),
                    ...(response.meta ?? {}),
                };

                this.filters.page = Number(this.meta.current_page ?? 1);
            } catch (error) {
                if (requestId !== this._listRequestId) {
                    return;
                }

                this.examinations = [];

                if (error instanceof ApiError && error.status === 403) {
                    this.listError =
                        "Bạn không có quyền xem danh sách phiếu khám.";
                } else {
                    this.listError =
                        error.message ?? "Không thể tải danh sách phiếu khám.";
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

        async applyFilters() {
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

        async searchPatients() {
            const requestId = ++this._patientSearchRequestId;
            const term = this.patientQuery.trim();

            if (term === "") {
                this.patientResults = [];

                return;
            }

            try {
                const response = await searchPatientOptions(term);

                if (requestId !== this._patientSearchRequestId) {
                    return;
                }

                this.patientResults = response.data ?? [];
            } catch {
                if (requestId === this._patientSearchRequestId) {
                    this.patientResults = [];
                }
            }
        },

        selectPatientFilter(patient) {
            this.filters.patient_id = patient.id;
            this.filters.patient_label = `${patient.full_name} (${patient.code})`;
            this.patientQuery = "";
            this.patientResults = [];

            this.applyFilters();
        },

        clearPatientFilter() {
            this.filters.patient_id = "";
            this.filters.patient_label = "";

            this.applyFilters();
        },

        resetForm() {
            this.form = emptyExaminationForm();
            this.formErrors = {};
            this.formMessage = "";
            this.editingId = null;
            this.appointmentQuery = "";
            this.appointmentResults = [];
        },

        openCreateModal() {
            if (!this.canCreate) {
                return;
            }

            this.resetForm();
            this.formMode = "create";
            this.formOpen = true;
        },

        openEditModal(examination) {
            if (!this.canUpdate) {
                return;
            }

            this.resetForm();

            this.formMode = "edit";
            this.editingId = examination.id;
            this.form = examinationToForm(examination);
            this.formOpen = true;
        },

        closeFormModal() {
            if (this.submitting) {
                return;
            }

            this.formOpen = false;
            this.resetForm();
        },

        async preselectAppointment(appointmentId) {
            this.preselecting = true;

            try {
                const response = await getAppointment(appointmentId);
                const appointment = response.data;

                if (appointment.status !== "confirmed") {
                    this.formMessage =
                        "Chỉ lịch hẹn đã xác nhận mới có thể tạo phiếu khám.";

                    return;
                }

                this.selectAppointment(appointment);
            } catch {
                this.formMessage = "Không tìm thấy lịch hẹn đã chọn.";
            } finally {
                this.preselecting = false;
            }
        },

        async searchAppointments() {
            const requestId = ++this._appointmentSearchRequestId;
            const term = this.appointmentQuery.trim();

            if (term === "") {
                this.appointmentResults = [];

                return;
            }

            this.searchingAppointment = true;

            try {
                const response = await searchConfirmedAppointments(term);

                if (requestId !== this._appointmentSearchRequestId) {
                    return;
                }

                this.appointmentResults = response.data ?? [];
            } catch {
                if (requestId === this._appointmentSearchRequestId) {
                    this.appointmentResults = [];
                }
            } finally {
                if (requestId === this._appointmentSearchRequestId) {
                    this.searchingAppointment = false;
                }
            }
        },

        selectAppointment(appointment) {
            this.form.appointment_id = appointment.id;
            this.form.appointment_label = `${appointment.patient?.full_name ?? "—"} · BS. ${appointment.doctor?.user?.name ?? "—"}`;
            this.appointmentQuery = "";
            this.appointmentResults = [];
        },

        clearAppointment() {
            this.form.appointment_id = null;
            this.form.appointment_label = "";
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

            if (!editing && !this.form.appointment_id) {
                this.formMessage = "Vui lòng chọn lịch hẹn đã xác nhận.";

                return;
            }

            this.submitting = true;
            this.formErrors = {};
            this.formMessage = "";

            try {
                if (editing) {
                    await updateExamination(
                        this.editingId,
                        editPayload(this.form),
                    );
                } else {
                    await createExamination(createPayload(this.form));
                }

                this.$store.ui.notify(
                    editing
                        ? "Cập nhật phiếu khám thành công."
                        : "Tạo phiếu khám thành công.",
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

        async openDetailModal(examination) {
            if (!this.canView) {
                return;
            }

            this.detailOpen = true;
            this.detailError = "";
            this.detailLoading = true;

            // Show the row data immediately, then replace it with the full record.
            this.detail = examination;

            try {
                const response = await getExamination(examination.id);

                this.detail = response.data;
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    this.detailError = "Không tìm thấy phiếu khám.";
                } else if (error instanceof ApiError && error.status === 403) {
                    this.detailError = "Bạn không có quyền xem phiếu khám này.";
                } else {
                    this.detailError =
                        error.message ?? "Không thể tải phiếu khám.";
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
            const examination = this.detail;

            if (!examination) {
                return;
            }

            this.closeDetailModal();
            this.openEditModal(examination);
        },

        handleEscape() {
            if (this.formOpen) {
                this.closeFormModal();

                return;
            }

            if (this.detailOpen) {
                this.closeDetailModal();
            }
        },

        formatDate,
        formatTime,
    };
}
