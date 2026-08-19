export function firstFieldError(errors, field) {
    const messages = errors?.[field];

    return Array.isArray(messages) ? messages[0] : null;
}
