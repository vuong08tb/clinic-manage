import { apiRequest } from '../../core/api-client';

export function getAppointments(filters) {
    const params = new URLSearchParams({
        page: String(filters.page),
        per_page: String(filters.per_page),
    });
    const search = filters.q.trim();

    if (search !== '') {
        params.set('q', search);
    }

    if (filters.date) {
        params.set('date', filters.date);
    }

    if (filters.status) {
        params.set('status', filters.status);
    }

    if (filters.doctor_id) {
        params.set('doctor_id', String(filters.doctor_id));
    }

    return apiRequest(`/appointments?${params.toString()}`);
}

export function getAppointment(appointmentId) {
    return apiRequest(`/appointments/${appointmentId}`);
}

export function createAppointment(payload) {
    return apiRequest('/appointments', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export function updateAppointment(appointmentId, payload) {
    return apiRequest(`/appointments/${appointmentId}`, {
        method: 'PATCH',
        body: JSON.stringify(payload),
    });
}

export function updateAppointmentStatus(appointmentId, status) {
    return apiRequest(`/appointments/${appointmentId}/status`, {
        method: 'PATCH',
        body: JSON.stringify({ status }),
    });
}

export function listDoctorOptions() {
    return apiRequest('/doctors?per_page=100');
}

export function searchPatientOptions(term) {
    const params = new URLSearchParams({ per_page: '10' });
    const search = term.trim();

    if (search !== '') {
        params.set('q', search);
    }

    return apiRequest(`/patients?${params.toString()}`);
}
