import { ApiError } from '../../core/api-error';
import {
    formatDate,
    formatDateOnly,
    genderLabel,
} from '../../core/formatters';
import { PERMISSIONS } from '../../core/permissions';
import { getPatient } from './patient-api';

export function patientShowPage(patientId) {
    return {
        patientId,
        patient: null,
        loading: true,
        error: '',

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.PATIENTS.FINDONE)) {
                this.$store.ui.notify(
                    'Bạn không có quyền xem bệnh nhân.',
                    'error',
                );

                window.location.replace('/dashboard');

                return;
            }

            await this.loadPatient();
        },

        async loadPatient() {
            this.loading = true;
            this.error = '';

            try {
                const response = await getPatient(this.patientId);

                this.patient = response.data;
            } catch (error) {
                this.patient = null;

                if (
                    error instanceof ApiError
                    && error.status === 404
                ) {
                    this.error = 'Không tìm thấy bệnh nhân.';
                } else if (
                    error instanceof ApiError
                    && error.status === 403
                ) {
                    this.error =
                        'Bạn không có quyền xem bệnh nhân này.';
                } else {
                    this.error =
                        error.message
                        ?? 'Không thể tải hồ sơ bệnh nhân.';
                }
            } finally {
                this.loading = false;
            }
        },

        formatDate,
        formatDateOnly,
        genderLabel,
    };
}
