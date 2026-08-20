export function emptyUserForm() {
    return {
        name: "",
        email: "",
        role_id: "",
        password: "",
        password_confirmation: "",
        changePassword: false,
    };
}

export function userToForm(user) {
    return {
        name: user.name ?? "",
        email: user.email ?? "",
        role_id: user.role?.id ?? "",
        password: "",
        password_confirmation: "",
        changePassword: false,
    };
}

export function createPayload(form) {
    return {
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
        password_confirmation: form.password_confirmation,
        role_id: form.role_id,
    };
}

// is_active is deliberately never sent here — the backend rejects it on
// create/update and requires the dedicated status endpoint instead.
export function editPayload(form) {
    const payload = {
        name: form.name.trim(),
        email: form.email.trim(),
        role_id: form.role_id,
    };

    if (form.changePassword && form.password) {
        payload.password = form.password;
        payload.password_confirmation = form.password_confirmation;
    }

    return payload;
}
