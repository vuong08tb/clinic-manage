import { localDateTimeInput } from '../../core/formatters';

export function emptyCreateForm() {
    return {
        patient_id: null,
        patient_label: '',
        doctor_id: '',
        scheduled_at: '',
        reason: '',
    };
}

export function createPayload(form) {
    return {
        patient_id: form.patient_id,
        doctor_id: Number(form.doctor_id),
        scheduled_at: new Date(form.scheduled_at).toISOString(),
        reason: form.reason.trim() || null,
    };
}

export function appointmentToEditForm(appointment) {
    return {
        scheduled_at: localDateTimeInput(new Date(appointment.scheduled_at)),
        reason: appointment.reason ?? '',
    };
}

export function editPayload(form) {
    return {
        scheduled_at: new Date(form.scheduled_at).toISOString(),
        reason: form.reason.trim() || null,
    };
}
