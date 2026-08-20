import { apiRequest } from "../../core/api-client";

export function getMedicines(filters) {
    const params = new URLSearchParams({
        page: String(filters.page),
        per_page: String(filters.per_page),
    });
    const search = filters.q.trim();

    if (search !== "") {
        params.set("q", search);
    }

    if (filters.stock_status) {
        params.set("stock_status", filters.stock_status);
    }

    return apiRequest(`/medicines?${params.toString()}`);
}

export function getMedicine(medicineId) {
    return apiRequest(`/medicines/${medicineId}`);
}

export function createMedicine(payload) {
    return apiRequest("/medicines", {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function updateMedicine(medicineId, payload) {
    return apiRequest(`/medicines/${medicineId}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
    });
}

export function deleteMedicine(medicineId) {
    return apiRequest(`/medicines/${medicineId}`, {
        method: "DELETE",
    });
}

export function adjustMedicineStock(medicineId, payload) {
    return apiRequest(`/medicines/${medicineId}/stock`, {
        method: "PATCH",
        body: JSON.stringify(payload),
    });
}
