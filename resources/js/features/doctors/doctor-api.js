import { apiRequest } from "../../core/api-client";

export function getDoctors(filters) {
    const params = new URLSearchParams({
        page: String(filters.page),
        per_page: String(filters.per_page),
    });
    const search = filters.q.trim();

    if (search !== "") {
        params.set("q", search);
    }

    if (filters.specialty_id) {
        params.set("specialty_id", String(filters.specialty_id));
    }

    return apiRequest(`/doctors?${params.toString()}`);
}

export function getDoctor(doctorId) {
    return apiRequest(`/doctors/${doctorId}`);
}

export function createDoctor(payload) {
    return apiRequest("/doctors", {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function updateDoctor(doctorId, payload) {
    return apiRequest(`/doctors/${doctorId}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
    });
}

export function deleteDoctor(doctorId) {
    return apiRequest(`/doctors/${doctorId}`, {
        method: "DELETE",
    });
}

export function listSpecialtyOptions() {
    return apiRequest("/specialties?per_page=100");
}

// Only users with the DOCTOR role are eligible to own a doctor profile
// (enforced server-side too) — filtered client-side since there is no
// role-name filter on GET /users, only role_id.
export async function searchDoctorEligibleUsers(term) {
    const params = new URLSearchParams({ per_page: "10" });
    const search = term.trim();

    if (search !== "") {
        params.set("q", search);
    }

    const response = await apiRequest(`/users?${params.toString()}`);

    return {
        ...response,
        data: (response.data ?? []).filter(
            (user) => user.role?.name === "DOCTOR",
        ),
    };
}
