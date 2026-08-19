const STATUS_LABELS = {
    scheduled: "Đã lên lịch",
    confirmed: "Đã xác nhận",
    completed: "Hoàn thành",
    cancelled: "Đã hủy",
    pending: "Đang chờ",
    paid: "Đã thanh toán",
    unpaid: "Chưa thanh toán",
    failed: "Thất bại",
};

const STATUS_CLASSES = {
    scheduled: "bg-amber-50 text-amber-700 ring-amber-600/20",
    pending: "bg-amber-50 text-amber-700 ring-amber-600/20",
    unpaid: "bg-amber-50 text-amber-700 ring-amber-600/20",
    confirmed: "bg-blue-50 text-blue-700 ring-blue-600/20",
    completed: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
    paid: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
    cancelled: "bg-rose-50 text-rose-700 ring-rose-600/20",
    failed: "bg-rose-50 text-rose-700 ring-rose-600/20",
};

const GENDER_LABELS = {
    male: "Nam",
    female: "Nữ",
    other: "Khác",
};

export function localDateInput(date = new Date()) {
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 10);
}

export function formatDate(date, options = {}) {
    if (!date) {
        return "—";
    }

    return new Intl.DateTimeFormat("vi-VN", {
        dateStyle: "long",
        ...options,
    }).format(new Date(date));
}

export function formatDateOnly(value) {
    if (!value) {
        return "—";
    }

    const [year, month, day] = String(value).split("-").map(Number);

    if (!year || !month || !day) {
        return "—";
    }

    return new Intl.DateTimeFormat("vi-VN").format(
        new Date(year, month - 1, day),
    );
}

export function formatTime(date) {
    if (!date) {
        return "—";
    }

    return new Intl.DateTimeFormat("vi-VN", {
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(date));
}

export function formatNumber(value) {
    return new Intl.NumberFormat("vi-VN").format(value ?? 0);
}

export function genderLabel(gender) {
    return GENDER_LABELS[gender] ?? "Không xác định";
}

export function statusLabel(status) {
    return STATUS_LABELS[status] ?? status ?? "Không xác định";
}

export function statusClasses(status) {
    return (
        STATUS_CLASSES[status] ?? "bg-slate-50 text-slate-700 ring-slate-600/20"
    );
}

export function localDateTimeInput(date = new Date()) {
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}
