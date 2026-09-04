export class ApiError extends Error {
    constructor(
        message,
        { status = 0, errors = {}, payload = null, retryAfter = null } = {},
    ) {
        super(message);
        this.name = "ApiError";
        this.status = status;
        this.errors = errors;
        this.payload = payload;
        this.retryAfter = retryAfter;
    }

    firstError(field) {
        const messages = this.errors?.[field];

        return Array.isArray(messages) ? messages[0] : null;
    }
}
