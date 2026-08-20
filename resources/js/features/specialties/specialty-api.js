import { apiRequest } from "../../core/api-client";

export function getSpecialties(filters) {
    const params = new URLSearchParams({
        page: String(filters.page),
        per_page: String(filters.per_page),
    });
    const search = filters.q.trim();

    if (search !== "") {
        params.set("q", search);
    }

    return apiRequest(`/specialties?${params.toString()}`);
}

export function getSpecialty(specialtyId) {
    return apiRequest(`/specialties/${specialtyId}`);
}

export function createSpecialty(payload) {
    return apiRequest("/specialties", {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function updateSpecialty(specialtyId, payload) {
    return apiRequest(`/specialties/${specialtyId}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
    });
}

export function deleteSpecialty(specialtyId) {
    return apiRequest(`/specialties/${specialtyId}`, {
        method: "DELETE",
    });
}
