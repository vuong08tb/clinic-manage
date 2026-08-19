export function emptyPatientForm() {
    return {
        full_name: '',
        gender: '',
        date_of_birth: '',
        phone: '',
        email: '',
        address: '',
    };
}

export function patientToForm(patient) {
    return {
        full_name: patient.full_name ?? '',
        gender: patient.gender ?? '',
        date_of_birth: patient.date_of_birth ?? '',
        phone: patient.phone ?? '',
        email: patient.email ?? '',
        address: patient.address ?? '',
    };
}

export function formToPayload(form) {
    return {
        full_name: form.full_name.trim(),
        gender: form.gender,
        date_of_birth: form.date_of_birth,
        phone: form.phone.trim(),
        email: form.email.trim() || null,
        address: form.address.trim() || null,
    };
}
