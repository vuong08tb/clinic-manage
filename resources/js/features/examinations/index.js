import { ApiError } from "../../core/api-error";
import { formatDate, formatTime } from "../../core/formatters";
import {
    calculateVisiblePages,
    emptyPaginationMeta,
} from "../../core/pagination";
import { PERMISSIONS } from "../../core/permissions";
import {
    getExaminations,
    listDoctorOptions,
    searchPatientOptions,
} from "./examination-api";

export function examinationIndexPage() {
    return {
        examinations: [],
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

        get canCreate() {
            return this.$store.auth.can(PERMISSIONS.EXAMINATIONS.CREATE);
        },

        get canView() {
            return this.$store.auth.can(PERMISSIONS.EXAMINATIONS.FINDONE);
        },

        get visiblePages() {
            return calculateVisiblePages(this.meta);
        },

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            if (!this.$store.auth.can(PERMISSIONS.EXAMINATIONS.FINDALL)) {
                this.$store.ui.notify(
                    "Bạn không có quyền xem danh sách phiếu khám.",
                    "error",
                );

                window.location.replace("/dashboard");

                return;
            }

            await Promise.all([this.loadDoctorOptions(), this.loadList()]);
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
                const response = await getExaminations(this.filters);

                if (requestId !== this._listRequestId) {
                    return;
                }

                this.examinations = response.data ?? [];
                this.meta = {
                    ...emptyPaginationMeta(),
                    ...(response.meta ?? {}),
                };

                this.filters.page = Number(this.meta.current_page ?? 1);
            } catch (error) {
                if (requestId !== this._listRequestId) {
                    return;
                }

                this.examinations = [];

                if (error instanceof ApiError && error.status === 403) {
                    this.listError =
                        "Bạn không có quyền xem danh sách phiếu khám.";
                } else {
                    this.listError =
                        error.message ?? "Không thể tải danh sách phiếu khám.";
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

        async changePerPage() {
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

        examinationDetailUrl(examination) {
            return `/examinations/${examination.id}`;
        },

        formatDate,
        formatTime,
    };
}
