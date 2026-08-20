import { apiRequest } from "../../core/api-client";

export function getUsers(filters) {
    const params = new URLSearchParams({
        page: String(filters.page),
        per_page: String(filters.per_page),
    });
    const search = filters.q.trim();

    if (search !== "") {
        params.set("q", search);
    }

    if (filters.role_id) {
        params.set("role_id", String(filters.role_id));
    }

    if (filters.is_active !== "") {
        params.set("is_active", filters.is_active);
    }

    return apiRequest(`/users?${params.toString()}`);
}

export function getUser(userId) {
    return apiRequest(`/users/${userId}`);
}

export function createUser(payload) {
    return apiRequest("/users", {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function updateUser(userId, payload) {
    return apiRequest(`/users/${userId}`, {
        method: "PATCH",
        body: JSON.stringify(payload),
    });
}

export function updateUserStatus(userId, isActive) {
    return apiRequest(`/users/${userId}/status`, {
        method: "PATCH",
        body: JSON.stringify({ is_active: isActive }),
    });
}

export function listRoleOptions() {
    return apiRequest("/roles");
}
