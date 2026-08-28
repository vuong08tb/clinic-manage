import { ApiError } from '../../core/api-error';
import { firstFieldError } from '../../core/form-errors';
import {
    formatDate,
    formatDateOnly,
    genderLabel,
    localDateInput,
} from '../../core/formatters';
import {
    calculateVisiblePages,
    emptyPaginationMeta,
} from '../../core/pagination';
import { PERMISSIONS } from '../../core/permissions';
import {
    createPatient,
    deletePatient,
    getPatient,
    getPatients,
    updatePatient,
} from './patient-api';
import {
    emptyPatientForm,
    formToPayload,
    patientToForm,
} from './patient-form';

export function patientIndexPage() {
    return {
        patients: [],
        meta: emptyPaginationMeta(),

        filters: {
            q: '',
            page: 1,
            per_page: 15,
        },

        loading: true,
        refreshing: false,
        listError: '',
        _listRequestId: 0,

        formOpen: false,
        formMode: 'create',
        editingId: null,
        editingCode: null,
        form: emptyPatientForm(),
        formErrors: {},
        formMessage: '',
        submitting: false,
        editingSnapshot: null,

        detailOpen: false,
        detail: null,
        detailLoading: false,
        detailError: '',

        deleteTarget: null,
        deleting: false,

        today: localDateInput(),

        get canView() {
            return this.$store.auth.can(PERMISSIONS.PATIENTS.FINDONE);
        },

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.PATIENTS.CREATE);
        },

        get canUpdate() {
            return this.$store.auth.can(PERMISSIONS.PATIENTS.UPDATE);
        },

        get canDelete() {
            return this.$store.auth.can(PERMISSIONS.PATIENTS.DELETE);
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.PATIENTS.FINDALL)) {
                this.$store.ui.notify(
                    'Bạn không có quyền xem danh sách bệnh nhân.',
                    'error',
                );

                window.location.replace('/dashboard');

                return;
            }

            await this.loadPatients();
        },

        async loadPatients() {
            const requestId = ++this._listRequestId;

            this.loading = true;
            this.listError = '';

            try {
                const response = await getPatients(this.filters);

                if (requestId !== this._listRequestId) {
                    return;
                }

                this.patients = response.data ?? [];
                this.meta = {
                    ...emptyPaginationMeta(),
                    ...(response.meta ?? {}),
                };

                this.filters.page = Number(
                    this.meta.current_page ?? 1,
                );
            } catch (error) {
                if (requestId !== this._listRequestId) {
                    return;
                }

                this.patients = [];

                if (
                    error instanceof ApiError
                    && error.status === 403
                ) {
                    this.listError =
                        'Bạn không có quyền xem danh sách bệnh nhân.';
                } else {
                    this.listError =
                        error.message
                        ?? 'Không thể tải danh sách bệnh nhân.';
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

            await this.loadPatients();
        },

        async search() {
            this.filters.page = 1;

            await this.loadPatients();
        },

        async changePerPage() {
            this.filters.page = 1;

            await this.loadPatients();
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

            await this.loadPatients();
        },

        get visiblePages() {
            return calculateVisiblePages(this.meta);
        },

        resetForm() {
            this.form = emptyPatientForm();
            this.formErrors = {};
            this.formMessage = '';
            this.editingId = null;
            this.editingCode = null;
            this.editingSnapshot = null;
        },

        _isUnchanged(payload) {
            return (
                this.editingSnapshot !== null &&
                JSON.stringify(payload) === JSON.stringify(this.editingSnapshot)
            );
        },

        openCreateModal() {
            if (!this.canCreate) {
                return;
            }

            this.resetForm();
            this.formMode = 'create';
            this.formOpen = true;
        },

        openEditModal(patient) {
            if (!this.canUpdate) {
                return;
            }

            this.resetForm();

            this.formMode = 'edit';
            this.editingId = patient.id;
            this.editingCode = patient.code;

            this.form = patientToForm(patient);
            this.editingSnapshot = formToPayload(this.form);

            this.formOpen = true;
        },

        closeFormModal() {
            if (this.submitting) {
                return;
            }

            this.formOpen = false;
            this.resetForm();
        },

        async openDetailModal(patient) {
            if (!this.canView) {
                return;
            }

            this.detailOpen = true;
            this.detailError = '';
            this.detailLoading = true;

            // Show the row data immediately, then replace it with the full record.
            this.detail = patient;

            try {
                const response = await getPatient(patient.id);

                this.detail = response.data;
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    this.detailError = 'Không tìm thấy bệnh nhân.';
                } else if (error instanceof ApiError && error.status === 403) {
                    this.detailError = 'Bạn không có quyền xem bệnh nhân này.';
                } else {
                    this.detailError =
                        error.message
                        ?? 'Không thể tải hồ sơ bệnh nhân.';
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
            const patient = this.detail;

            if (!patient) {
                return;
            }

            this.closeDetailModal();
            this.openEditModal(patient);
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

            const payload = formToPayload(this.form);

            if (editing && this._isUnchanged(payload)) {
                this.formOpen = false;
                this.resetForm();

                return;
            }

            this.submitting = true;
            this.formErrors = {};
            this.formMessage = '';

            try {
                if (editing) {
                    await updatePatient(this.editingId, payload);
                } else {
                    await createPatient(payload);
                }

                this.$store.ui.notify(
                    editing
                        ? 'Cập nhật bệnh nhân thành công.'
                        : 'Thêm bệnh nhân thành công.',
                    'success',
                );

                this.formOpen = false;
                this.resetForm();

                if (!editing) {
                    this.filters.page = 1;
                }

                await this.loadPatients();
            } catch (error) {
                if (error instanceof ApiError) {
                    this.formErrors = error.errors ?? {};

                    if (error.status === 403) {
                        this.formMessage =
                            'Bạn không có quyền thực hiện thao tác này.';

                        this.$store.ui.notify(
                            this.formMessage,
                            'error',
                        );
                    } else {
                        this.formMessage = error.message;
                    }
                } else {
                    this.formMessage =
                        'Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.';
                }
            } finally {
                this.submitting = false;
            }
        },

        fieldError(field) {
            return firstFieldError(this.formErrors, field);
        },

        askDelete(patient) {
            if (!this.canDelete) {
                return;
            }

            this.deleteTarget = patient;
        },

        cancelDelete() {
            if (this.deleting) {
                return;
            }

            this.deleteTarget = null;
        },

        async confirmDelete() {
            if (
                !this.deleteTarget
                || this.deleting
                || !this.canDelete
            ) {
                return;
            }

            this.deleting = true;

            try {
                await deletePatient(this.deleteTarget.id);

                this.$store.ui.notify(
                    'Xóa bệnh nhân thành công.',
                    'success',
                );

                this.deleteTarget = null;

                if (this.detailOpen) {
                    this.closeDetailModal();
                }

                if (
                    this.patients.length === 1
                    && this.filters.page > 1
                ) {
                    this.filters.page -= 1;
                }

                await this.loadPatients();
            } catch (error) {
                this.$store.ui.notify(
                    error.message ?? 'Không thể xóa bệnh nhân.',
                    'error',
                );
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
        formatDateOnly,
        genderLabel,
    };
}
