import { ApiError } from "../../core/api-error";
import { firstFieldError } from "../../core/form-errors";
import {
    formatCurrency,
    formatDate,
    statusClasses,
    statusLabel,
} from "../../core/formatters";
import {
    calculateVisiblePages,
    emptyPaginationMeta,
} from "../../core/pagination";
import { PERMISSIONS } from "../../core/permissions";
import {
    cancelInvoice,
    createInvoice,
    getExamination,
    getInvoice,
    getInvoices,
    searchExaminationsByPatient,
    searchPatientOptions,
    updateInvoiceDiscount,
} from "./invoice-api";
import {
    createPayload,
    editPayload,
    emptyInvoiceForm,
    invoiceToForm,
} from "./invoice-form";
import { createPayment, getPayments } from "../payments/payment-api";

export function invoiceIndexPage() {
    return {
        invoices: [],
        meta: emptyPaginationMeta(),
        loading: true,
        refreshing: false,
        listError: "",
        _listRequestId: 0,

        filters: {
            status: "",
            page: 1,
            per_page: 15,
        },

        formOpen: false,
        formMode: "create",
        editingId: null,
        form: emptyInvoiceForm(),
        formErrors: {},
        formMessage: "",
        submitting: false,
        preselecting: false,

        // Two-step examination picker used inside the create form when there is no
        // ?examination_id= deep link: search patient, then pick one of their exams.
        examinationPickerPatientQuery: "",
        examinationPickerPatientResults: [],
        _examinationPickerPatientRequestId: 0,
        examinationPickerExaminations: [],
        examinationPickerLoading: false,

        detailOpen: false,
        detail: null,
        detailLoading: false,
        detailError: "",

        payments: [],
        paymentsLoading: false,
        paymentsError: "",

        paymentForm: { method: "paypal", amount: "" },
        paymentSubmitting: false,
        paymentMessage: "",

        cancelTarget: null,
        cancelling: false,

        get canView() {
            return this.$store.auth.can(PERMISSIONS.INVOICES.FINDONE);
        },

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.INVOICES.CREATE);
        },

        get canUpdate() {
            return this.$store.auth.can(PERMISSIONS.INVOICES.UPDATE);
        },

        get canUpdateStatus() {
            return this.$store.auth.can(PERMISSIONS.INVOICES.UPDATESTATUS);
        },

        get canCreatePayment() {
            return this.$store.auth.can(PERMISSIONS.PAYMENTS.CREATE);
        },

        get visiblePages() {
            return calculateVisiblePages(this.meta);
        },

        get completedPaymentsTotal() {
            return this.payments
                .filter((payment) => payment.status === "completed")
                .reduce((sum, payment) => sum + Number(payment.amount), 0);
        },

        get remainingBalance() {
            if (!this.detail) {
                return 0;
            }

            return Math.max(
                0,
                Number(this.detail.total) - this.completedPaymentsTotal,
            );
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.INVOICES.FINDALL)) {
                this.$store.ui.notify(
                    "Bạn không có quyền xem danh sách hóa đơn.",
                    "error",
                );

                window.location.replace("/dashboard");

                return;
            }

            await this.loadList();
            await this.openFromQueryString();
        },

        // Deep link from the examination list: /invoices?examination_id=123 opens the
        // create form; /invoices?invoice_id=456 (e.g. from the PayPal return page)
        // opens that invoice's detail directly.
        async openFromQueryString() {
            const params = new URLSearchParams(window.location.search);
            const examinationId = params.get("examination_id");
            const invoiceId = params.get("invoice_id");

            if (invoiceId) {
                window.history.replaceState({}, "", "/invoices");

                if (this.canView) {
                    await this.openDetailModal({ id: Number(invoiceId) });
                }

                return;
            }

            if (!examinationId) {
                return;
            }

            window.history.replaceState({}, "", "/invoices");

            if (!this.canCreate) {
                return;
            }

            this.openCreateModal();

            await this.preselectExamination(Number(examinationId));
        },

        async preselectExamination(examinationId) {
            this.preselecting = true;

            try {
                const response = await getExamination(examinationId);

                this.selectExamination(response.data);
            } catch {
                this.formMessage = "Không tìm thấy phiếu khám đã chọn.";
            } finally {
                this.preselecting = false;
            }
        },

        async loadList() {
            const requestId = ++this._listRequestId;

            this.loading = true;
            this.listError = "";

            try {
                const response = await getInvoices(this.filters);

                if (requestId !== this._listRequestId) {
                    return;
                }

                this.invoices = response.data ?? [];
                this.meta = {
                    ...emptyPaginationMeta(),
                    ...(response.meta ?? {}),
                };

                this.filters.page = Number(this.meta.current_page ?? 1);
            } catch (error) {
                if (requestId !== this._listRequestId) {
                    return;
                }

                this.invoices = [];

                if (error instanceof ApiError && error.status === 403) {
                    this.listError = "Bạn không có quyền xem danh sách hóa đơn.";
                } else {
                    this.listError =
                        error.message ?? "Không thể tải danh sách hóa đơn.";
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

        // --- Examination picker (create form) ---

        async searchExaminationPickerPatients() {
            const requestId = ++this._examinationPickerPatientRequestId;
            const term = this.examinationPickerPatientQuery.trim();

            if (term === "") {
                this.examinationPickerPatientResults = [];

                return;
            }

            try {
                const response = await searchPatientOptions(term);

                if (requestId !== this._examinationPickerPatientRequestId) {
                    return;
                }

                this.examinationPickerPatientResults = response.data ?? [];
            } catch {
                if (requestId === this._examinationPickerPatientRequestId) {
                    this.examinationPickerPatientResults = [];
                }
            }
        },

        async pickExaminationPickerPatient(patient) {
            this.examinationPickerPatientQuery = "";
            this.examinationPickerPatientResults = [];
            this.examinationPickerLoading = true;

            try {
                const response = await searchExaminationsByPatient(patient.id);

                this.examinationPickerExaminations = response.data ?? [];
            } catch {
                this.examinationPickerExaminations = [];
            } finally {
                this.examinationPickerLoading = false;
            }
        },

        selectExamination(examination) {
            this.form.examination_id = examination.id;
            this.form.examination_label =
                `${examination.patient?.full_name ?? "—"} · BS. ${examination.doctor?.user?.name ?? "—"}` +
                (examination.diagnosis ? ` · ${examination.diagnosis}` : "");
            this.examinationPickerPatientQuery = "";
            this.examinationPickerPatientResults = [];
            this.examinationPickerExaminations = [];
        },

        clearExamination() {
            this.form.examination_id = null;
            this.form.examination_label = "";
        },

        // --- Create/edit form (discount only in edit mode) ---

        resetForm() {
            this.form = emptyInvoiceForm();
            this.formErrors = {};
            this.formMessage = "";
            this.editingId = null;
            this.examinationPickerPatientQuery = "";
            this.examinationPickerPatientResults = [];
            this.examinationPickerExaminations = [];
        },

        openCreateModal() {
            if (!this.canCreate) {
                return;
            }

            this.resetForm();
            this.formMode = "create";
            this.formOpen = true;
        },

        openEditModal(invoice) {
            if (!this.canUpdate || invoice.status !== "unpaid") {
                return;
            }

            this.resetForm();

            this.formMode = "edit";
            this.editingId = invoice.id;
            this.form = invoiceToForm(invoice);
            this.formOpen = true;
        },

        closeFormModal() {
            if (this.submitting) {
                return;
            }

            this.formOpen = false;
            this.resetForm();
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

            if (!editing && !this.form.examination_id) {
                this.formMessage = "Vui lòng chọn phiếu khám.";

                return;
            }

            this.submitting = true;
            this.formErrors = {};
            this.formMessage = "";

            try {
                if (editing) {
                    await updateInvoiceDiscount(
                        this.editingId,
                        editPayload(this.form),
                    );
                } else {
                    await createInvoice(createPayload(this.form));
                }

                this.$store.ui.notify(
                    editing
                        ? "Cập nhật hóa đơn thành công."
                        : "Tạo hóa đơn thành công.",
                    "success",
                );

                this.formOpen = false;
                this.resetForm();

                if (!editing) {
                    this.filters.page = 1;
                }

                await this.loadList();

                if (editing && this.detailOpen && this.detail?.id === this.editingId) {
                    await this.reloadDetail();
                }
            } catch (error) {
                if (error instanceof ApiError) {
                    this.formErrors = error.errors ?? {};
                    this.formMessage =
                        error.status === 403
                            ? "Bạn không có quyền thực hiện thao tác này."
                            : (firstFieldError(error.errors, "invoice") ??
                                firstFieldError(error.errors, "examination") ??
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

        editFromDetail() {
            const invoice = this.detail;

            if (!invoice) {
                return;
            }

            this.closeDetailModal();
            this.openEditModal(invoice);
        },

        // --- Detail modal ---

        async openDetailModal(invoice) {
            if (!this.canView) {
                return;
            }

            this.detailOpen = true;
            this.detailError = "";
            this.detailLoading = true;
            this.detail = invoice;
            this.payments = [];
            this.paymentForm = { method: "paypal", amount: "" };
            this.paymentMessage = "";

            try {
                const response = await getInvoice(invoice.id);

                this.detail = response.data;
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    this.detailError = "Không tìm thấy hóa đơn.";
                } else if (error instanceof ApiError && error.status === 403) {
                    this.detailError = "Bạn không có quyền xem hóa đơn này.";
                } else {
                    this.detailError = error.message ?? "Không thể tải hóa đơn.";
                }
            } finally {
                this.detailLoading = false;
            }

            if (!this.detailError) {
                await this.loadPayments();
            }
        },

        closeDetailModal() {
            this.detailOpen = false;
            this.detail = null;
            this.detailError = "";
            this.payments = [];
            this.paymentMessage = "";
        },

        async reloadDetail() {
            if (!this.detail?.id) {
                return;
            }

            try {
                const response = await getInvoice(this.detail.id);

                this.detail = response.data;
            } catch (error) {
                this.detailError =
                    error instanceof ApiError
                        ? error.message
                        : "Không thể tải hóa đơn.";
            }

            await this.loadPayments();
            await this.loadList();
        },

        async loadPayments() {
            if (!this.detail?.id || !this.$store.auth.can(PERMISSIONS.PAYMENTS.FINDALL)) {
                return;
            }

            this.paymentsLoading = true;
            this.paymentsError = "";

            try {
                const response = await getPayments({ invoice_id: this.detail.id });

                this.payments = response.data ?? [];

                if (this.paymentForm.amount === "" && this.remainingBalance > 0) {
                    this.paymentForm.amount = String(this.remainingBalance);
                }
            } catch (error) {
                this.payments = [];
                this.paymentsError =
                    error instanceof ApiError
                        ? error.message
                        : "Không thể tải lịch sử thanh toán.";
            } finally {
                this.paymentsLoading = false;
            }
        },

        // --- Create payment (redirects the browser to PayPal's hosted checkout) ---

        async submitPayment() {
            if (this.paymentSubmitting || !this.detail?.id) {
                return;
            }

            const amount = Number(this.paymentForm.amount);

            if (!(amount > 0)) {
                this.paymentMessage = "Vui lòng nhập số tiền lớn hơn 0.";

                return;
            }

            if (amount > this.remainingBalance) {
                this.paymentMessage = `Số tiền vượt quá số dư còn lại (${formatCurrency(this.remainingBalance)}).`;

                return;
            }

            this.paymentSubmitting = true;
            this.paymentMessage = "";

            try {
                const response = await createPayment(this.detail.id, {
                    amount,
                    method: this.paymentForm.method,
                });

                const approvalUrl = response.data?.approval_url;

                if (!approvalUrl) {
                    this.paymentMessage =
                        "Không nhận được liên kết thanh toán từ PayPal.";
                    this.paymentSubmitting = false;

                    return;
                }

                // Full-page redirect to PayPal's hosted checkout — the buyer approves
                // there, then PayPal sends them back to /payments/return or /payments/cancel.
                window.location.href = approvalUrl;
            } catch (error) {
                this.paymentMessage =
                    error instanceof ApiError
                        ? (firstFieldError(error.errors, "invoice") ??
                            firstFieldError(error.errors, "amount") ??
                            error.message)
                        : "Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.";
                this.paymentSubmitting = false;
            }
        },

        // --- Cancel confirm ---

        askCancel(invoice) {
            if (!this.canUpdateStatus || invoice.status !== "unpaid") {
                return;
            }

            this.cancelTarget = invoice;
        },

        cancelDismiss() {
            if (this.cancelling) {
                return;
            }

            this.cancelTarget = null;
        },

        async confirmCancel() {
            if (!this.cancelTarget || this.cancelling || !this.canUpdateStatus) {
                return;
            }

            this.cancelling = true;

            try {
                await cancelInvoice(this.cancelTarget.id);

                this.$store.ui.notify("Hủy hóa đơn thành công.", "success");

                const cancelledId = this.cancelTarget.id;

                this.cancelTarget = null;

                if (this.detailOpen && this.detail?.id === cancelledId) {
                    await this.reloadDetail();
                }

                await this.loadList();
            } catch (error) {
                const message =
                    error instanceof ApiError
                        ? (firstFieldError(error.errors, "invoice") ??
                            firstFieldError(error.errors, "status") ??
                            error.message)
                        : "Không thể hủy hóa đơn.";

                this.$store.ui.notify(message, "error");
            } finally {
                this.cancelling = false;
            }
        },

        handleEscape() {
            if (this.cancelTarget) {
                this.cancelDismiss();

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
        formatCurrency,
        statusLabel,
        statusClasses,
    };
}
