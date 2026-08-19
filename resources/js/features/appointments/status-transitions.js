const ALLOWED_TRANSITIONS = {
    scheduled: ['confirmed', 'cancelled'],
    confirmed: ['completed', 'cancelled'],
    completed: [],
    cancelled: [],
};

export function allowedNextStatuses(status) {
    return ALLOWED_TRANSITIONS[status] ?? [];
}
