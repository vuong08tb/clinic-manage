import { apiRequest } from '../../core/api-client';
import {
    formatDate,
    formatNumber,
    formatTime,
    localDateInput,
    statusClasses,
    statusLabel,
} from '../../core/formatters';
import { PERMISSIONS } from '../../core/permissions';

const WIDGETS = [
    {
        key: 'patients',
        label: 'Bệnh nhân',
        caption: 'Tổng hồ sơ',
        permission: PERMISSIONS.PATIENTS.FINDALL,
        endpoint: '/patients?per_page=1',
        tone: 'blue',
    },
    {
        key: 'appointments',
        label: 'Lịch hôm nay',
        caption: 'Cuộc hẹn',
        permission: PERMISSIONS.APPOINTMENTS.FINDALL,
        endpoint: () => `/appointments?date=${localDateInput()}&per_page=1`,
        tone: 'teal',
    },
    {
        key: 'examinations',
        label: 'Phiếu khám',
        caption: 'Tổng phiếu',
        permission: PERMISSIONS.EXAMINATIONS.FINDALL,
        endpoint: '/examinations?per_page=1',
        tone: 'violet',
    },
    {
        key: 'medicines',
        label: 'Thuốc hết hàng',
        caption: 'Cần xử lý',
        permission: 'MEDICINES.FINDALL',
        endpoint: '/medicines?stock_status=out_of_stock&per_page=1',
        tone: 'amber',
    },
    {
        key: 'invoices',
        label: 'Hóa đơn chưa thu',
        caption: 'Đang chờ',
        permission: 'INVOICES.FINDALL',
        endpoint: '/invoices?status=unpaid&per_page=1',
        tone: 'rose',
    },
    {
        key: 'users',
        label: 'Người dùng',
        caption: 'Đang hoạt động',
        permission: 'USERS.FINDALL',
        endpoint: '/users?is_active=1&per_page=1',
        tone: 'slate',
    },
];

export function dashboardPage() {
    return {
        widgets: [],
        appointments: [],
        appointmentsLoading: false,
        appointmentsError: '',
        refreshing: false,
        todayLabel: formatDate(new Date()),

        get canCreatePatient() {
            return this.$store.auth.can(PERMISSIONS.PATIENTS.CREATE);
        },

        get canCreateAppointment() {
            return this.$store.auth.can(PERMISSIONS.APPOINTMENTS.CREATE);
        },

        get canViewAppointments() {
            return this.$store.auth.can(PERMISSIONS.APPOINTMENTS.FINDALL);
        },

        get canCreateInvoice() {
            return this.$store.auth.can(PERMISSIONS.INVOICES.CREATE);
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            await this.load();
        },

        async load() {
            if (this.refreshing) {
                return;
            }

            this.refreshing = true;
            this.widgets = WIDGETS
                .filter((widget) => this.$store.auth.can(widget.permission))
                .map((widget) => ({ ...widget, value: null, loading: true, error: false }));

            await Promise.all([
                ...this.widgets.map((widget) => this.loadWidget(widget)),
                this.loadAppointments(),
            ]);

            this.refreshing = false;
        },

        async loadWidget(widget) {
            try {
                const endpoint = typeof widget.endpoint === 'function'
                    ? widget.endpoint()
                    : widget.endpoint;
                const response = await apiRequest(endpoint);
                widget.value = response.meta?.total ?? response.data?.length ?? 0;
            } catch {
                widget.error = true;
            } finally {
                widget.loading = false;
            }
        },

        async loadAppointments() {
            if (!this.$store.auth.can(PERMISSIONS.APPOINTMENTS.FINDALL)) {
                this.appointments = [];
                return;
            }

            this.appointmentsLoading = true;
            this.appointmentsError = '';

            try {
                const response = await apiRequest(
                    `/appointments?date=${localDateInput()}&per_page=8`,
                );
                this.appointments = response.data ?? [];
            } catch (error) {
                this.appointmentsError = error.message ?? 'Không thể tải lịch hẹn hôm nay.';
            } finally {
                this.appointmentsLoading = false;
            }
        },

        widgetValue(widget) {
            return widget.error ? '—' : formatNumber(widget.value);
        },

        formatTime,
        statusLabel,
        statusClasses,
    };
}
