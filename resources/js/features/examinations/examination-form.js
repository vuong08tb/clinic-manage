export function emptyExaminationForm() {
    return {
        appointment_id: null,
        appointment_label: "",
        diagnosis: "",
        notes: "",
    };
}

export function examinationToForm(examination) {
    return {
        appointment_id: examination.appointment_id ?? null,
        appointment_label: `${examination.patient?.full_name ?? "—"} · BS. ${examination.doctor?.user?.name ?? "—"}`,
        diagnosis: examination.diagnosis ?? "",
        notes: examination.notes ?? "",
    };
}

export function createPayload(form) {
    return {
        appointment_id: form.appointment_id,
        diagnosis: form.diagnosis.trim(),
        notes: form.notes.trim() || null,
    };
}

export function editPayload(form) {
    return {
        diagnosis: form.diagnosis.trim(),
        notes: form.notes.trim() || null,
    };
}
