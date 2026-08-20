export function emptySpecialtyForm() {
    return {
        name: "",
        description: "",
    };
}

export function specialtyToForm(specialty) {
    return {
        name: specialty.name ?? "",
        description: specialty.description ?? "",
    };
}

export function formToPayload(form) {
    return {
        name: form.name.trim(),
        description: form.description.trim() || null,
    };
}
