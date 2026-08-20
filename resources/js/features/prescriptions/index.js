import { ApiError } from "../../core/api-error";
import { firstFieldError } from "../../core/form-errors";
import { formatCurrency, formatDate, formatNumber } from "../../core/formatters";
import {
    calculateVisiblePages,
    emptyPaginationMeta,
} from "../../core/pagination";
import { PERMISSIONS } from "../../core/permissions";
import {
    addPrescriptionItem,
    createPrescription,
    getExamination,
    getPrescription,
    getPrescriptions,
    listDoctorOptions,
    removePrescriptionItem,
    searchExaminationsByPatient,
    searchMedicineOptions,
    searchPatientOptions,
    updatePrescriptionItem,
} from "./prescription-api";
import {
    addItemPayload,
    createPayload,
    emptyItemDraft,
    emptyPrescriptionForm,
    nextDraftKey,
    updateItemPayload,
} from "./prescription-form";

export function prescriptionIndexPage() {
    return {
        prescriptions: [],
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

        // Create modal. formMode stays "create" — prescriptions have no
        // whole-record update endpoint yet, only the item-level mutations below.
        formOpen: false,
        formMode: "create",
        editingId: null,
        form: emptyPrescriptionForm(),
        formErrors: {},
        formMessage: "",
        submitting: false,
        preselecting: false,

        // Two-step picker used inside the create form when there is no
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

        // Medicine item builder shared between the create form (pushes to
        // form.items locally) and the detail modal (posts to the item API) —
        // only one of the two modals is ever open at the same time.
        itemDraft: emptyItemDraft(),
        medicineQuery: "",
        medicineResults: [],
        searchingMedicine: false,
        _medicineSearchRequestId: 0,
        addingItem: false,
        itemDraftError: "",

        editingItemId: null,
        itemEditDraft: null,
        itemEditErrors: {},
        itemEditSubmitting: false,

        deleteItemTarget: null,
        deletingItem: false,

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.PRESCRIPTIONS.CREATE);
        },

        get canView() {
            return this.$store.auth.can(PERMISSIONS.PRESCRIPTIONS.FINDONE);
        },

        get canAddItem() {
            return this.$store.auth.can(PERMISSIONS.PRESCRIPTIONS.ADDITEM);
        },

        get canUpdateItem() {
            return this.$store.auth.can(PERMISSIONS.PRESCRIPTIONS.UPDATEITEM);
        },

        get canRemoveItem() {
            return this.$store.auth.can(PERMISSIONS.PRESCRIPTIONS.REMOVEITEM);
        },

        get visiblePages() {
            return calculateVisiblePages(this.meta);
        },

        get draftExceedsStock() {
            return (
                this.itemDraft.medicine_id &&
                Number(this.itemDraft.quantity) > Number(this.itemDraft.stock)
            );
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.PRESCRIPTIONS.FINDALL)) {
                this.$store.ui.notify(
                    "Bạn không có quyền xem danh sách toa thuốc.",
                    "error",
                );

                window.location.replace("/dashboard");

                return;
            }

            await Promise.all([this.loadDoctorOptions(), this.loadList()]);

            await this.openCreateFromQueryString();
        },

        // Deep link from the examination list: /prescriptions?examination_id=123
        async openCreateFromQueryString() {
            const examinationId = new URLSearchParams(
                window.location.search,
            ).get("examination_id");

            if (!examinationId) {
                return;
            }

            window.history.replaceState({}, "", "/prescriptions");

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
                const response = await getPrescriptions(this.filters);

                if (requestId !== this._listRequestId) {
                    return;
                }

                this.prescriptions = response.data ?? [];
                this.meta = {
                    ...emptyPaginationMeta(),
                    ...(response.meta ?? {}),
                };

                this.filters.page = Number(this.meta.current_page ?? 1);
            } catch (error) {
                if (requestId !== this._listRequestId) {
                    return;
                }

                this.prescriptions = [];

                if (error instanceof ApiError && error.status === 403) {
                    this.listError =
                        "Bạn không có quyền xem danh sách toa thuốc.";
                } else {
                    this.listError =
                        error.message ?? "Không thể tải danh sách toa thuốc.";
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

        // --- Examination picker (create form, manual flow) ---

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
                const response = await searchExaminationsByPatient(
                    patient.id,
                );

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

        // --- Medicine item builder (shared by create form and detail modal) ---

        async searchMedicines() {
            const requestId = ++this._medicineSearchRequestId;
            const term = this.medicineQuery.trim();

            if (term === "") {
                this.medicineResults = [];

                return;
            }

            this.searchingMedicine = true;

            try {
                const response = await searchMedicineOptions(term);

                if (requestId !== this._medicineSearchRequestId) {
                    return;
                }

                this.medicineResults = response.data ?? [];
            } catch {
                if (requestId === this._medicineSearchRequestId) {
                    this.medicineResults = [];
                }
            } finally {
                if (requestId === this._medicineSearchRequestId) {
                    this.searchingMedicine = false;
                }
            }
        },

        selectMedicineForDraft(medicine) {
            this.itemDraft.medicine_id = medicine.id;
            this.itemDraft.medicine_label = `${medicine.name} (${medicine.code})`;
            this.itemDraft.unit = medicine.unit;
            this.itemDraft.stock = medicine.stock;
            this.medicineQuery = "";
            this.medicineResults = [];
        },

        clearMedicineDraft() {
            this.itemDraft = emptyItemDraft();
            this.itemDraftError = "";
        },

        describeItemError(error) {
            if (error instanceof ApiError) {
                if (error.status === 403) {
                    return "Bạn không có quyền thực hiện thao tác này.";
                }

                return (
                    firstFieldError(error.errors, "medicine_id") ??
                    firstFieldError(error.errors, "quantity") ??
                    firstFieldError(error.errors, "items") ??
                    error.message
                );
            }

            return "Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.";
        },

        async addItemFromDraft() {
            this.itemDraftError = "";

            if (!this.itemDraft.medicine_id) {
                this.itemDraftError = "Vui lòng chọn thuốc.";

                return;
            }

            if (!this.itemDraft.dosage.trim()) {
                this.itemDraftError = "Vui lòng nhập liều dùng.";

                return;
            }

            if (!(Number(this.itemDraft.quantity) > 0)) {
                this.itemDraftError = "Số lượng phải lớn hơn 0.";

                return;
            }

            if (this.draftExceedsStock) {
                this.itemDraftError = `Số lượng vượt quá tồn kho hiện có (${this.itemDraft.stock}).`;

                return;
            }

            if (this.detailOpen) {
                if (!this.canAddItem) {
                    this.$store.ui.notify(
                        "Bạn không có quyền thêm thuốc vào toa.",
                        "error",
                    );

                    return;
                }

                if (
                    (this.detail?.items ?? []).some(
                        (row) => row.medicine_id === this.itemDraft.medicine_id,
                    )
                ) {
                    this.itemDraftError = "Thuốc này đã có trong toa.";

                    return;
                }

                this.addingItem = true;

                try {
                    await addPrescriptionItem(
                        this.detail.id,
                        addItemPayload(this.itemDraft),
                    );

                    this.$store.ui.notify(
                        "Thêm thuốc vào toa thành công.",
                        "success",
                    );

                    this.clearMedicineDraft();

                    await this.reloadDetail();
                } catch (error) {
                    this.itemDraftError = this.describeItemError(error);
                } finally {
                    this.addingItem = false;
                }

                return;
            }

            if (
                this.form.items.some(
                    (row) => row.medicine_id === this.itemDraft.medicine_id,
                )
            ) {
                this.itemDraftError = "Thuốc này đã có trong toa.";

                return;
            }

            // Create-mode: keep the item local until the prescription itself is submitted.
            this.form.items.push({ _key: nextDraftKey(), ...this.itemDraft });
            this.clearMedicineDraft();
        },

        removeDraftItem(item) {
            this.form.items = this.form.items.filter(
                (row) => row._key !== item._key,
            );
        },

        // --- Persisted item inline edit (detail modal only) ---

        startEditItem(item) {
            if (!this.canUpdateItem) {
                return;
            }

            this.editingItemId = item.id;
            this.itemEditDraft = {
                quantity: item.quantity,
                dosage: item.dosage,
                usage_instruction: item.usage_instruction ?? "",
            };
            this.itemEditErrors = {};
        },

        cancelEditItem() {
            if (this.itemEditSubmitting) {
                return;
            }

            this.editingItemId = null;
            this.itemEditDraft = null;
            this.itemEditErrors = {};
        },

        async saveEditItem(item) {
            if (this.itemEditSubmitting || !this.canUpdateItem) {
                return;
            }

            const availableStock = item.medicine?.stock ?? 0;

            if (
                Number(this.itemEditDraft.quantity) >
                availableStock + item.quantity
            ) {
                this.itemEditErrors = {
                    quantity: [
                        `Số lượng vượt quá tồn kho hiện có (${availableStock + item.quantity}).`,
                    ],
                };

                return;
            }

            this.itemEditSubmitting = true;
            this.itemEditErrors = {};

            try {
                await updatePrescriptionItem(
                    this.detail.id,
                    item.id,
                    updateItemPayload(this.itemEditDraft),
                );

                this.$store.ui.notify(
                    "Cập nhật thuốc trong toa thành công.",
                    "success",
                );

                this.cancelEditItem();

                await this.reloadDetail();
            } catch (error) {
                if (error instanceof ApiError) {
                    this.itemEditErrors = error.errors ?? {};
                }
            } finally {
                this.itemEditSubmitting = false;
            }
        },

        itemFieldError(field) {
            return firstFieldError(this.itemEditErrors, field);
        },

        askDeleteItem(item) {
            if (!this.canRemoveItem) {
                return;
            }

            this.deleteItemTarget = item;
        },

        cancelDeleteItem() {
            if (this.deletingItem) {
                return;
            }

            this.deleteItemTarget = null;
        },

        async confirmDeleteItem() {
            if (
                !this.deleteItemTarget ||
                this.deletingItem ||
                !this.canRemoveItem
            ) {
                return;
            }

            this.deletingItem = true;

            try {
                await removePrescriptionItem(
                    this.detail.id,
                    this.deleteItemTarget.id,
                );

                this.$store.ui.notify(
                    "Xóa thuốc khỏi toa thành công.",
                    "success",
                );

                this.deleteItemTarget = null;

                await this.reloadDetail();
            } catch (error) {
                this.$store.ui.notify(
                    error instanceof ApiError
                        ? error.message
                        : "Không thể xóa thuốc khỏi toa.",
                    "error",
                );
            } finally {
                this.deletingItem = false;
            }
        },

        async reloadDetail() {
            if (!this.detail?.id) {
                return;
            }

            try {
                const response = await getPrescription(this.detail.id);

                this.detail = response.data;
            } catch (error) {
                this.detailError =
                    error instanceof ApiError
                        ? error.message
                        : "Không thể tải toa thuốc.";
            }

            await this.loadList();
        },

        // --- Create form ---

        resetForm() {
            this.form = emptyPrescriptionForm();
            this.formErrors = {};
            this.formMessage = "";
            this.editingId = null;
            this.examinationPickerPatientQuery = "";
            this.examinationPickerPatientResults = [];
            this.examinationPickerExaminations = [];
            this.medicineQuery = "";
            this.medicineResults = [];
            this.clearMedicineDraft();
        },

        openCreateModal() {
            if (!this.canCreate) {
                return;
            }

            this.resetForm();
            this.formMode = "create";
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
            if (this.submitting || !this.canCreate) {
                return;
            }

            if (!this.form.examination_id) {
                this.formMessage = "Vui lòng chọn phiếu khám.";

                return;
            }

            this.submitting = true;
            this.formErrors = {};
            this.formMessage = "";

            try {
                await createPrescription(createPayload(this.form));

                this.$store.ui.notify("Tạo toa thuốc thành công.", "success");

                this.formOpen = false;
                this.resetForm();
                this.filters.page = 1;

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

        // --- Detail modal ---

        async openDetailModal(prescription) {
            if (!this.canView) {
                return;
            }

            this.detailOpen = true;
            this.detailError = "";
            this.detailLoading = true;
            this.detail = prescription;
            this.clearMedicineDraft();

            try {
                const response = await getPrescription(prescription.id);

                this.detail = response.data;
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    this.detailError = "Không tìm thấy toa thuốc.";
                } else if (error instanceof ApiError && error.status === 403) {
                    this.detailError = "Bạn không có quyền xem toa thuốc này.";
                } else {
                    this.detailError =
                        error.message ?? "Không thể tải toa thuốc.";
                }
            } finally {
                this.detailLoading = false;
            }
        },

        closeDetailModal() {
            this.detailOpen = false;
            this.detail = null;
            this.detailError = "";
            this.cancelEditItem();
            this.deleteItemTarget = null;
            this.clearMedicineDraft();
        },

        handleEscape() {
            if (this.deleteItemTarget) {
                this.cancelDeleteItem();

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
        formatNumber,
        formatCurrency,
    };
}
