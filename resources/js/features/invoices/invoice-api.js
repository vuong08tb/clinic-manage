import { apiRequest } from "../../core/api-client";

export function getInvoices(filters) {
    const params = new URLSearchParams({
        page: String(filters.page),
        per_page: String(filters.per_page),
    });

    if (filters.status) {
        params.set("status", filters.status);
    }

    if (filters.examination_id) {
        params.set("examination_id", String(filters.examination_id));
    }

    return apiRequest(`/invoices?${params.toString()}`);
}

export function getInvoice(invoiceId) {
    return apiRequest(`/invoices/${invoiceId}`);
}

export function createInvoice(payload) {
    return apiRequest("/invoices", {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function updateInvoiceDiscount(invoiceId, payload) {
    return apiRequest(`/invoices/${invoiceId}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
    });
}

export function cancelInvoice(invoiceId) {
    return apiRequest(`/invoices/${invoiceId}/status`, {
        method: "PATCH",
        body: JSON.stringify({ status: "cancelled" }),
    });
}

export function getExamination(examinationId) {
    return apiRequest(`/examinations/${examinationId}`);
}

export function searchExaminationsByPatient(patientId) {
    return apiRequest(`/examinations?patient_id=${patientId}&per_page=20`);
}

export function searchPatientOptions(term) {
    const params = new URLSearchParams({ per_page: "10" });
    const search = term.trim();

    if (search !== "") {
        params.set("q", search);
    }

    return apiRequest(`/patients?${params.toString()}`);
}
