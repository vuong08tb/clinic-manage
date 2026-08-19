import { apiRequest } from "../../core/api-client";

export function getExaminations(filters) {
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

    return apiRequest(`/examinations?${params.toString()}`);
}

export function getExamination(examinationId) {
    return apiRequest(`/examinations/${examinationId}`);
}

export function createExamination(payload) {
    return apiRequest("/examinations", {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function updateExamination(examinationId, payload) {
    return apiRequest(`/examinations/${examinationId}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
    });
}

export function getAppointment(appointmentId) {
    return apiRequest(`/appointments/${appointmentId}`);
}

export function searchConfirmedAppointments(term) {
    const params = new URLSearchParams({ status: "confirmed", per_page: "10" });
    const search = term.trim();

    if (search !== "") {
        params.set("q", search);
    }

    return apiRequest(`/appointments?${params.toString()}`);
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
