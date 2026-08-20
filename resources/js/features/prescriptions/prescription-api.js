import { apiRequest } from "../../core/api-client";

export function getPrescriptions(filters) {
    const params = new URLSearchParams({
        page: String(filters.page),
        per_page: String(filters.per_page),
    });

    if (filters.doctor_id) {
        params.set("doctor_id", String(filters.doctor_id));
    }

    if (filters.patient_id) {
        params.set("patient_id", String(filters.patient_id));
    }

    return apiRequest(`/prescriptions?${params.toString()}`);
}

export function getPrescription(prescriptionId) {
    return apiRequest(`/prescriptions/${prescriptionId}`);
}

export function createPrescription(payload) {
    return apiRequest("/prescriptions", {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function addPrescriptionItem(prescriptionId, payload) {
    return apiRequest(`/prescriptions/${prescriptionId}/items`, {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function updatePrescriptionItem(prescriptionId, itemId, payload) {
    return apiRequest(`/prescriptions/${prescriptionId}/items/${itemId}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
    });
}

export function removePrescriptionItem(prescriptionId, itemId) {
    return apiRequest(`/prescriptions/${prescriptionId}/items/${itemId}`, {
        method: "DELETE",
    });
}

export function getExamination(examinationId) {
    return apiRequest(`/examinations/${examinationId}`);
}

export function searchExaminationsByPatient(patientId) {
    return apiRequest(`/examinations?patient_id=${patientId}&per_page=20`);
}

export function listDoctorOptions() {
    return apiRequest("/doctors?per_page=100");
}

export function searchPatientOptions(term) {
    const params = new URLSearchParams({ per_page: "10" });
    const search = term.trim();

    if (search !== "") {
        params.set("q", search);
    }

    return apiRequest(`/patients?${params.toString()}`);
}

export function searchMedicineOptions(term) {
    const params = new URLSearchParams({
        per_page: "10",
        stock_status: "in_stock",
    });
    const search = term.trim();

    if (search !== "") {
        params.set("q", search);
    }

    return apiRequest(`/medicines?${params.toString()}`);
}
