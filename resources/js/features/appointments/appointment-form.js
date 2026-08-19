import {
    fromLocalDateTimeInput,
    localDateTimeInput,
} from '../../core/formatters';

export function emptyAppointmentForm() {
    return {
        patient_id: null,
        patient_label: '',
        doctor_id: '',
        scheduled_at: '',
        reason: '',
    };
}

export function appointmentToForm(appointment) {
    const patient = appointment.patient;

    return {
        patient_id: patient?.id ?? null,
        patient_label: patient
            ? `${patient.full_name} (${patient.code})`
            : '',
        doctor_id: appointment.doctor?.id ?? '',
        scheduled_at: localDateTimeInput(new Date(appointment.scheduled_at)),
        reason: appointment.reason ?? '',
    };
}

export function createPayload(form) {
    return {
        patient_id: form.patient_id,
        doctor_id: Number(form.doctor_id),
        scheduled_at: fromLocalDateTimeInput(form.scheduled_at).toISOString(),
        reason: form.reason.trim() || null,
    };
}

export function editPayload(form) {
    return {
        scheduled_at: fromLocalDateTimeInput(form.scheduled_at).toISOString(),
        reason: form.reason.trim() || null,
    };
}
