import { ApiError } from '../../core/api-error';
import { firstFieldError } from '../../core/form-errors';
import { PERMISSIONS } from '../../core/permissions';
import {
    createExamination,
    getAppointment,
    searchConfirmedAppointments,
} from './examination-api';
import { createPayload, emptyCreateForm } from './examination-form';

export function examinationCreatePage() {
    return {
        form: emptyCreateForm(),
        errors: {},
        message: '',
        submitting: false,
        preselecting: false,

        appointmentQuery: '',
        appointmentResults: [],
        searchingAppointment: false,
        _appointmentSearchRequestId: 0,

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.EXAMINATIONS.CREATE);
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.canCreate) {
                this.$store.ui.notify('Bạn không có quyền tạo phiếu khám.', 'error');

                window.location.replace('/examinations');

                return;
            }

            const appointmentId = new URLSearchParams(window.location.search).get('appointment_id');

            if (appointmentId) {
                await this.preselectAppointment(Number(appointmentId));
            }
        },

        async preselectAppointment(appointmentId) {
            this.preselecting = true;

            try {
                const response = await getAppointment(appointmentId);
                const appointment = response.data;

                if (appointment.status !== 'confirmed') {
                    this.message = 'Chỉ lịch hẹn đã xác nhận mới có thể tạo phiếu khám.';

                    return;
                }

                this.selectAppointment(appointment);
            } catch {
                this.message = 'Không tìm thấy lịch hẹn đã chọn.';
            } finally {
                this.preselecting = false;
            }
        },

        async searchAppointments() {
            const requestId = ++this._appointmentSearchRequestId;
            const term = this.appointmentQuery.trim();

            if (term === '') {
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
            this.form.appointment_label = `${appointment.patient?.full_name ?? '—'} · BS. ${appointment.doctor?.user?.name ?? '—'}`;
            this.appointmentQuery = '';
            this.appointmentResults = [];
        },

        clearAppointment() {
            this.form.appointment_id = null;
            this.form.appointment_label = '';
        },

        async submit() {
            if (this.submitting || !this.canCreate) {
                return;
            }

            if (!this.form.appointment_id) {
                this.message = 'Vui lòng chọn lịch hẹn đã xác nhận.';

                return;
            }

            this.submitting = true;
            this.errors = {};
            this.message = '';

            try {
                const response = await createExamination(createPayload(this.form));

                this.$store.ui.notify('Tạo phiếu khám thành công.', 'success');

                window.location.replace(`/examinations/${response.data.id}`);
            } catch (error) {
                if (error instanceof ApiError) {
                    this.errors = error.errors ?? {};

                    if (error.status === 403) {
                        this.message = 'Bạn không có quyền thực hiện thao tác này.';
                    } else {
                        this.message = error.message;
                    }
                } else {
                    this.message = 'Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.';
                }
            } finally {
                this.submitting = false;
            }
        },

        fieldError(field) {
            return firstFieldError(this.errors, field);
        },
    };
}
