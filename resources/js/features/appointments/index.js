import { ApiError } from '../../core/api-error';
import { firstFieldError } from '../../core/form-errors';
import {
    formatDate,
    formatTime,
    fromClinicClock,
    localDateInput,
    statusClasses,
    statusLabel,
    toClinicClock,
} from '../../core/formatters';
import {
    calculateVisiblePages,
    emptyPaginationMeta,
} from '../../core/pagination';
import { PERMISSIONS } from '../../core/permissions';
import {
    createAppointment,
    getAppointment,
    getAppointments,
    listDoctorOptions,
    searchPatientOptions,
    updateAppointment,
    updateAppointmentStatus,
} from './appointment-api';
import {
    appointmentToForm,
    createPayload,
    editPayload,
    emptyAppointmentForm,
} from './appointment-form';
import { allowedNextStatuses } from './status-transitions';

// The week grid is built on clinic days, so the boundaries are computed against
// the clinic wall clock rather than whatever timezone the browser happens to be in.
function startOfWeek(date) {
    const result = toClinicClock(date);
    const day = result.getUTCDay();
    const diffToMonday = day === 0 ? -6 : 1 - day;

    result.setUTCHours(0, 0, 0, 0);
    result.setUTCDate(result.getUTCDate() + diffToMonday);

    return fromClinicClock(result);
}

function addDays(date, amount) {
    const result = toClinicClock(date);
    result.setUTCDate(result.getUTCDate() + amount);

    return fromClinicClock(result);
}

function isoDate(date) {
    return localDateInput(date);
}

const WEEKDAY_LABELS = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

export function appointmentIndexPage() {
    return {
        view: 'list',

        appointments: [],
        meta: emptyPaginationMeta(),
        loading: true,
        refreshing: false,
        listError: '',
        _listRequestId: 0,

        filters: {
            q: '',
            date: '',
            status: '',
            doctor_id: '',
            page: 1,
            per_page: 15,
        },

        doctorOptions: [],

        weekStart: startOfWeek(new Date()),
        weekDays: [],
        weekLoading: false,
        weekError: '',

        formOpen: false,
        formMode: 'create',
        editingId: null,
        form: emptyAppointmentForm(),
        formErrors: {},
        formMessage: '',
        submitting: false,

        patientQuery: '',
        patientResults: [],
        patientSearching: false,
        _patientSearchRequestId: 0,

        detailOpen: false,
        detail: null,
        detailLoading: false,
        detailError: '',

        statusTarget: null,
        updatingStatus: false,

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.APPOINTMENTS.CREATE);
        },

        get canUpdate() {
            return this.$store.auth.can(PERMISSIONS.APPOINTMENTS.UPDATE);
        },

        get canUpdateStatus() {
            return this.$store.auth.can(PERMISSIONS.APPOINTMENTS.UPDATESTATUS);
        },

        get canView() {
            return this.$store.auth.can(PERMISSIONS.APPOINTMENTS.FINDONE);
        },

        get canCreateExamination() {
            return this.$store.auth.can(PERMISSIONS.EXAMINATIONS.CREATE);
        },

        get visiblePages() {
            return calculateVisiblePages(this.meta);
        },

        get weekRangeLabel() {
            const end = addDays(this.weekStart, 6);

            return `${formatDate(this.weekStart, { dateStyle: 'medium' })} – ${formatDate(end, { dateStyle: 'medium' })}`;
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.APPOINTMENTS.FINDALL)) {
                this.$store.ui.notify(
                    'Bạn không có quyền xem danh sách lịch hẹn.',
                    'error',
                );

                window.location.replace('/dashboard');

                return;
            }

            await Promise.all([
                this.loadDoctorOptions(),
                this.loadList(),
            ]);
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
            this.listError = '';

            try {
                const response = await getAppointments(this.filters);

                if (requestId !== this._listRequestId) {
                    return;
                }

                this.appointments = response.data ?? [];
                this.meta = {
                    ...emptyPaginationMeta(),
                    ...(response.meta ?? {}),
                };

                this.filters.page = Number(this.meta.current_page ?? 1);
            } catch (error) {
                if (requestId !== this._listRequestId) {
                    return;
                }

                this.appointments = [];

                if (error instanceof ApiError && error.status === 403) {
                    this.listError = 'Bạn không có quyền xem danh sách lịch hẹn.';
                } else {
                    this.listError = error.message ?? 'Không thể tải danh sách lịch hẹn.';
                }
            } finally {
                if (requestId === this._listRequestId) {
                    this.loading = false;
                    this.refreshing = false;
                }
            }
        },

        async reloadCurrentView() {
            if (this.view === 'calendar') {
                await this.loadWeek();

                return;
            }

            await this.loadList();
        },

        async refresh() {
            if (this.refreshing) {
                return;
            }

            this.refreshing = true;

            if (this.view === 'calendar') {
                await this.loadWeek();
                this.refreshing = false;

                return;
            }

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

        async changePerPage() {
            this.filters.page = 1;

            await this.loadList();
        },

        async goToPage(page) {
            const targetPage = Number(page);

            if (
                targetPage < 1
                || targetPage > this.meta.last_page
                || targetPage === this.meta.current_page
            ) {
                return;
            }

            this.filters.page = targetPage;

            await this.loadList();
        },

        async switchView(view) {
            if (this.view === view) {
                return;
            }

            this.view = view;

            if (view === 'calendar') {
                await this.loadWeek();
            } else {
                await this.loadList();
            }
        },

        async loadWeek() {
            this.weekLoading = true;
            this.weekError = '';

            try {
                const days = Array.from({ length: 7 }, (_, index) => {
                    const date = addDays(this.weekStart, index);

                    return { date, dateStr: isoDate(date) };
                });

                const responses = await Promise.all(
                    days.map(({ dateStr }) => getAppointments({
                        ...this.filters,
                        date: dateStr,
                        page: 1,
                        per_page: 50,
                    })),
                );

                this.weekDays = days.map(({ date, dateStr }, index) => ({
                    date,
                    dateStr,
                    label: WEEKDAY_LABELS[index],
                    appointments: responses[index].data ?? [],
                }));
            } catch (error) {
                this.weekDays = [];
                this.weekError = error.message ?? 'Không thể tải lịch tuần.';
            } finally {
                this.weekLoading = false;
            }
        },

        async previousWeek() {
            this.weekStart = addDays(this.weekStart, -7);

            await this.loadWeek();
        },

        async nextWeek() {
            this.weekStart = addDays(this.weekStart, 7);

            await this.loadWeek();
        },

        async goToCurrentWeek() {
            this.weekStart = startOfWeek(new Date());

            await this.loadWeek();
        },

        resetForm() {
            this.form = emptyAppointmentForm();
            this.formErrors = {};
            this.formMessage = '';
            this.editingId = null;
            this.patientQuery = '';
            this.patientResults = [];
        },

        openCreateModal() {
            if (!this.canCreate) {
                return;
            }

            this.resetForm();
            this.formMode = 'create';
            this.formOpen = true;
        },

        // Backend only accepts scheduled_at/reason updates while the appointment is still scheduled.
        canEdit(appointment) {
            return this.canUpdate && appointment?.status === 'scheduled';
        },

        openEditModal(appointment) {
            if (!this.canEdit(appointment)) {
                return;
            }

            this.resetForm();

            this.formMode = 'edit';
            this.editingId = appointment.id;
            this.form = appointmentToForm(appointment);
            this.formOpen = true;
        },

        closeFormModal() {
            if (this.submitting) {
                return;
            }

            this.formOpen = false;
            this.resetForm();
        },

        async searchPatients() {
            const requestId = ++this._patientSearchRequestId;
            const term = this.patientQuery.trim();

            if (term === '') {
                this.patientResults = [];

                return;
            }

            this.patientSearching = true;

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
            } finally {
                if (requestId === this._patientSearchRequestId) {
                    this.patientSearching = false;
                }
            }
        },

        selectPatient(patient) {
            this.form.patient_id = patient.id;
            this.form.patient_label = `${patient.full_name} (${patient.code})`;
            this.patientQuery = '';
            this.patientResults = [];
        },

        clearSelectedPatient() {
            this.form.patient_id = null;
            this.form.patient_label = '';
        },

        async submitForm() {
            if (this.submitting) {
                return;
            }

            const editing = this.formMode === 'edit';
            const allowed = editing ? this.canUpdate : this.canCreate;

            if (!allowed) {
                this.$store.ui.notify(
                    'Bạn không có quyền thực hiện thao tác này.',
                    'error',
                );

                return;
            }

            if (!editing && (!this.form.patient_id || !this.form.doctor_id)) {
                this.formMessage = 'Vui lòng chọn bệnh nhân và bác sĩ.';

                return;
            }

            this.submitting = true;
            this.formErrors = {};
            this.formMessage = '';

            try {
                if (editing) {
                    await updateAppointment(
                        this.editingId,
                        editPayload(this.form),
                    );
                } else {
                    await createAppointment(createPayload(this.form));
                }

                this.$store.ui.notify(
                    editing
                        ? 'Cập nhật lịch hẹn thành công.'
                        : 'Tạo lịch hẹn thành công.',
                    'success',
                );

                this.formOpen = false;
                this.resetForm();

                if (!editing) {
                    this.filters.page = 1;
                }

                await this.reloadCurrentView();
            } catch (error) {
                if (error instanceof ApiError) {
                    this.formErrors = error.errors ?? {};
                    this.formMessage = error.status === 403
                        ? 'Bạn không có quyền thực hiện thao tác này.'
                        : error.message;
                } else {
                    this.formMessage = 'Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.';
                }
            } finally {
                this.submitting = false;
            }
        },

        fieldError(field) {
            return firstFieldError(this.formErrors, field);
        },

        async openDetailModal(appointment) {
            if (!this.canView) {
                return;
            }

            this.detailOpen = true;
            this.detailError = '';
            this.detailLoading = true;

            // Show the row data immediately, then replace it with the full record.
            this.detail = appointment;

            try {
                const response = await getAppointment(appointment.id);

                this.detail = response.data;
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    this.detailError = 'Không tìm thấy lịch hẹn.';
                } else if (error instanceof ApiError && error.status === 403) {
                    this.detailError = 'Bạn không có quyền xem lịch hẹn này.';
                } else {
                    this.detailError = error.message ?? 'Không thể tải lịch hẹn.';
                }
            } finally {
                this.detailLoading = false;
            }
        },

        closeDetailModal() {
            this.detailOpen = false;
            this.detail = null;
            this.detailError = '';
        },

        editFromDetail() {
            const appointment = this.detail;

            if (!appointment) {
                return;
            }

            this.closeDetailModal();
            this.openEditModal(appointment);
        },

        allowedNextStatuses(status) {
            return allowedNextStatuses(status);
        },

        // Status buttons live in the list row, so the permission check happens here.
        statusActions(appointment) {
            if (!this.canUpdateStatus) {
                return [];
            }

            return allowedNextStatuses(appointment?.status);
        },

        askStatusChange(appointment, status) {
            if (!this.canUpdateStatus || !appointment) {
                return;
            }

            this.statusTarget = { appointment, status };
        },

        cancelStatusChange() {
            if (this.updatingStatus) {
                return;
            }

            this.statusTarget = null;
        },

        async confirmStatusChange() {
            if (!this.statusTarget || this.updatingStatus) {
                return;
            }

            const { appointment, status } = this.statusTarget;

            this.updatingStatus = true;

            try {
                const response = await updateAppointmentStatus(
                    appointment.id,
                    status,
                );

                if (this.detail?.id === appointment.id) {
                    this.detail = response.data;
                }

                this.statusTarget = null;

                this.$store.ui.notify('Cập nhật trạng thái thành công.', 'success');

                await this.reloadCurrentView();
            } catch (error) {
                this.$store.ui.notify(
                    error.message ?? 'Không thể cập nhật trạng thái.',
                    'error',
                );
            } finally {
                this.updatingStatus = false;
            }
        },

        examinationCreateUrl(appointment) {
            return `/examinations?appointment_id=${appointment?.id}`;
        },

        handleEscape() {
            if (this.statusTarget) {
                this.cancelStatusChange();

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
        formatTime,
        statusLabel,
        statusClasses,
    };
}
