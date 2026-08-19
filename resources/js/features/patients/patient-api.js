import { apiRequest } from '../../core/api-client';

export function getPatients(filters) {
    const params = new URLSearchParams({
        page: String(filters.page),
        per_page: String(filters.per_page),
    });
    const search = filters.q.trim();

    if (search !== '') {
        params.set('q', search);
    }

    return apiRequest(`/patients?${params.toString()}`);
}

export function getPatient(patientId) {
    return apiRequest(`/patients/${patientId}`);
}

export function createPatient(payload) {
    return apiRequest('/patients', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export function updatePatient(patientId, payload) {
    return apiRequest(`/patients/${patientId}`, {
        method: 'PATCH',
        body: JSON.stringify(payload),
    });
}

export function deletePatient(patientId) {
    return apiRequest(`/patients/${patientId}`, {
        method: 'DELETE',
    });
}
