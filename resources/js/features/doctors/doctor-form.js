export function emptyDoctorForm() {
    return {
        user_id: null,
        user_label: "",
        specialty_id: "",
        license_number: "",
        bio: "",
    };
}

export function doctorToForm(doctor) {
    return {
        user_id: doctor.user_id ?? null,
        user_label: doctor.user
            ? `${doctor.user.name} (${doctor.user.email})`
            : "",
        specialty_id: doctor.specialty_id ?? "",
        license_number: doctor.license_number ?? "",
        bio: doctor.bio ?? "",
    };
}

export function formToPayload(form) {
    return {
        user_id: form.user_id,
        specialty_id: form.specialty_id,
        license_number: form.license_number.trim(),
        bio: form.bio.trim() || null,
    };
}
