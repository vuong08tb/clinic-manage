export function emptyCreateForm() {
    return {
        appointment_id: null,
        appointment_label: "",
        diagnosis: "",
        notes: "",
    };
}

export function createPayload(form) {
    return {
        appointment_id: form.appointment_id,
        diagnosis: form.diagnosis.trim(),
        notes: form.notes.trim() || null,
    };
}

export function examinationToEditForm(examination) {
    return {
        diagnosis: examination.diagnosis ?? "",
        notes: examination.notes ?? "",
    };
}

export function editPayload(form) {
    return {
        diagnosis: form.diagnosis.trim(),
        notes: form.notes.trim() || null,
    };
}
