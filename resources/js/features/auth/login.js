import { ApiError } from '../../core/api-error';
import { hasAuthToken } from '../../core/auth-storage';

export function loginPage() {
    return {
        form: {
            email: '',
            password: '',
        },
        errors: {},
        message: '',
        submitting: false,
        checkingSession: true,

        async init() {
            if (!hasAuthToken()) {
                this.checkingSession = false;
                return;
            }

            const user = await this.$store.auth.bootstrap({ redirectIfGuest: false });

            if (user) {
                window.location.replace('/dashboard');
                return;
            }

            this.checkingSession = false;
        },

        async submit() {
            if (this.submitting) {
                return;
            }

            this.submitting = true;
            this.errors = {};
            this.message = '';

            try {
                await this.$store.auth.login(this.form);
                window.location.replace('/dashboard');
            } catch (error) {
                if (error instanceof ApiError) {
                    this.errors = error.errors;
                    this.message = error.message;
                } else {
                    this.message = 'Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.';
                }
            } finally {
                this.submitting = false;
            }
        },

        fieldError(field) {
            const messages = this.errors?.[field];

            return Array.isArray(messages) ? messages[0] : null;
        },
    };
}
