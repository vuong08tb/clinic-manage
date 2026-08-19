import { ApiError } from '../../core/api-error';
import { firstFieldError } from '../../core/form-errors';
import { formatDate } from '../../core/formatters';
import { PERMISSIONS } from '../../core/permissions';
import { getExamination, updateExamination } from './examination-api';
import { editPayload, examinationToEditForm } from './examination-form';

export function examinationShowPage(examinationId) {
    return {
        examinationId,
        examination: null,
        loading: true,
        error: '',

        form: null,
        errors: {},
        message: '',
        submitting: false,

        get canUpdate() {
            return this.$store.auth.can(PERMISSIONS.EXAMINATIONS.UPDATE);
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.EXAMINATIONS.FINDONE)) {
                this.$store.ui.notify('Bạn không có quyền xem phiếu khám.', 'error');

                window.location.replace('/examinations');

                return;
            }

            await this.load();
        },

        async load() {
            this.loading = true;
            this.error = '';

            try {
                const response = await getExamination(this.examinationId);

                this.examination = response.data;
                this.form = this.canUpdate ? examinationToEditForm(this.examination) : null;
            } catch (error) {
                this.examination = null;

                if (error instanceof ApiError && error.status === 404) {
                    this.error = 'Không tìm thấy phiếu khám.';
                } else if (error instanceof ApiError && error.status === 403) {
                    this.error = 'Bạn không có quyền xem phiếu khám này.';
                } else {
                    this.error = error.message ?? 'Không thể tải phiếu khám.';
                }
            } finally {
                this.loading = false;
            }
        },

        async submit() {
            if (this.submitting || !this.form) {
                return;
            }

            this.submitting = true;
            this.errors = {};
            this.message = '';

            try {
                const response = await updateExamination(
                    this.examinationId,
                    editPayload(this.form),
                );

                this.examination = response.data;
                this.form = examinationToEditForm(this.examination);

                this.$store.ui.notify('Cập nhật phiếu khám thành công.', 'success');
            } catch (error) {
                if (error instanceof ApiError) {
                    this.errors = error.errors ?? {};
                    this.message = error.status === 403
                        ? 'Bạn không có quyền thực hiện thao tác này.'
                        : error.message;
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

        formatDate,
    };
}
