import { ApiError } from '../../core/api-error';
import { firstFieldError } from '../../core/form-errors';
import {
    formatDate,
    formatTime,
    statusClasses,
    statusLabel,
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
    appointmentToEditForm,
    createPayload,
    editPayload,
    emptyCreateForm,
} from './appointment-form';
import { allowedNextStatuses } from './status-transitions';

function startOfWeek(date) {
    const result = new Date(date);
    const day = result.getDay();
    const diffToMonday = day === 0 ? -6 : 1 - day;

    result.setHours(0, 0, 0, 0);
    result.setDate(result.getDate() + diffToMonday);

    return result;
}

function addDays(date, amount) {
    const result = new Date(date);
    result.setDate(result.getDate() + amount);

    return result;
}

function isoDate(date) {
    return date.toISOString().slice(0, 10);
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

        createOpen: false,
        createForm: emptyCreateForm(),
        createErrors: {},
        createMessage: '',
        creating: false,

        patientQuery: '',
        patientResults: [],
        patientSearching: false,
        _patientSearchRequestId: 0,

        drawerOpen: false,
        selected: null,
        editForm: null,
        editErrors: {},
        editMessage: '',
        editing: false,

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

        openCreateModal() {
            if (!this.canCreate) {
                return;
            }

            this.createForm = emptyCreateForm();
            this.createErrors = {};
            this.createMessage = '';
            this.patientQuery = '';
            this.patientResults = [];
            this.createOpen = true;
        },

        closeCreateModal() {
            if (this.creating) {
                return;
            }

            this.createOpen = false;
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
            this.createForm.patient_id = patient.id;
            this.createForm.patient_label = `${patient.full_name} (${patient.code})`;
            this.patientQuery = '';
            this.patientResults = [];
        },

        clearSelectedPatient() {
            this.createForm.patient_id = null;
            this.createForm.patient_label = '';
        },

        async submitCreate() {
            if (this.creating || !this.canCreate) {
                return;
            }

            if (!this.createForm.patient_id || !this.createForm.doctor_id) {
                this.createMessage = 'Vui lòng chọn bệnh nhân và bác sĩ.';

                return;
            }

            this.creating = true;
            this.createErrors = {};
            this.createMessage = '';

            try {
                await createAppointment(createPayload(this.createForm));

                this.$store.ui.notify('Tạo lịch hẹn thành công.', 'success');

                this.createOpen = false;
                this.filters.page = 1;

                if (this.view === 'calendar') {
                    await this.loadWeek();
                } else {
                    await this.loadList();
                }
            } catch (error) {
                if (error instanceof ApiError) {
                    this.createErrors = error.errors ?? {};

                    if (error.status === 403) {
                        this.createMessage = 'Bạn không có quyền thực hiện thao tác này.';
                    } else {
                        this.createMessage = error.message;
                    }
                } else {
                    this.createMessage = 'Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.';
                }
            } finally {
                this.creating = false;
            }
        },

        createFieldError(field) {
            return firstFieldError(this.createErrors, field);
        },

        async openDetail(appointment) {
            if (!this.canView) {
                return;
            }

            this.drawerOpen = true;
            this.editErrors = {};
            this.editMessage = '';

            try {
                const response = await getAppointment(appointment.id);

                this.selected = response.data;
            } catch {
                this.selected = appointment;
            }

            this.editForm = this.selected.status === 'scheduled' && this.canUpdate
                ? appointmentToEditForm(this.selected)
                : null;
        },

        closeDrawer() {
            if (this.editing || this.updatingStatus) {
                return;
            }

            this.drawerOpen = false;
            this.selected = null;
            this.editForm = null;
            this.statusTarget = null;
        },

        async submitEdit() {
            if (this.editing || !this.editForm || !this.selected) {
                return;
            }

            this.editing = true;
            this.editErrors = {};
            this.editMessage = '';

            try {
                const response = await updateAppointment(
                    this.selected.id,
                    editPayload(this.editForm),
                );

                this.selected = response.data;
                this.editForm = appointmentToEditForm(this.selected);

                this.$store.ui.notify('Cập nhật lịch hẹn thành công.', 'success');

                if (this.view === 'calendar') {
                    await this.loadWeek();
                } else {
                    await this.loadList();
                }
            } catch (error) {
                if (error instanceof ApiError) {
                    this.editErrors = error.errors ?? {};
                    this.editMessage = error.status === 403
                        ? 'Bạn không có quyền thực hiện thao tác này.'
                        : error.message;
                } else {
                    this.editMessage = 'Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.';
                }
            } finally {
                this.editing = false;
            }
        },

        editFieldError(field) {
            return firstFieldError(this.editErrors, field);
        },

        allowedNextStatuses(status) {
            return allowedNextStatuses(status);
        },

        askStatusChange(status) {
            if (!this.canUpdateStatus) {
                return;
            }

            this.statusTarget = status;
        },

        cancelStatusChange() {
            if (this.updatingStatus) {
                return;
            }

            this.statusTarget = null;
        },

        async confirmStatusChange() {
            if (!this.statusTarget || this.updatingStatus || !this.selected) {
                return;
            }

            this.updatingStatus = true;

            try {
                const response = await updateAppointmentStatus(
                    this.selected.id,
                    this.statusTarget,
                );

                this.selected = response.data;
                this.statusTarget = null;

                this.$store.ui.notify('Cập nhật trạng thái thành công.', 'success');

                if (this.view === 'calendar') {
                    await this.loadWeek();
                } else {
                    await this.loadList();
                }
            } catch (error) {
                this.$store.ui.notify(
                    error.message ?? 'Không thể cập nhật trạng thái.',
                    'error',
                );
            } finally {
                this.updatingStatus = false;
            }
        },

        formatDate,
        formatTime,
        statusLabel,
        statusClasses,
    };
}
