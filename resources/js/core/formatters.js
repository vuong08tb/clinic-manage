const CLINIC_TIMEZONE = "Asia/Ho_Chi_Minh";

// Vietnam has been a fixed UTC+7 with no daylight saving since 1975, so a plain
// offset is enough to convert between an instant and the clinic wall clock.
const CLINIC_OFFSET_MS = 7 * 60 * 60 * 1000;

// Shifts an instant so its UTC getters read as the clinic wall clock.
function toClinicClock(date) {
    return new Date(date.getTime() + CLINIC_OFFSET_MS);
}

// Turns a clinic wall clock back into the instant it represents.
function fromClinicClock(date) {
    return new Date(date.getTime() - CLINIC_OFFSET_MS);
}

const STATUS_LABELS = {
    scheduled: "Đã lên lịch",
    confirmed: "Đã xác nhận",
    completed: "Hoàn thành",
    cancelled: "Đã hủy",
    pending: "Đang chờ",
    paid: "Đã thanh toán",
    unpaid: "Chưa thanh toán",
    failed: "Thất bại",
    active: "Đang bán",
    inactive: "Ngừng bán",
};

const STATUS_CLASSES = {
    scheduled: "bg-amber-50 text-amber-700 ring-amber-600/20",
    pending: "bg-amber-50 text-amber-700 ring-amber-600/20",
    unpaid: "bg-amber-50 text-amber-700 ring-amber-600/20",
    confirmed: "bg-blue-50 text-blue-700 ring-blue-600/20",
    completed: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
    paid: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
    active: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
    cancelled: "bg-rose-50 text-rose-700 ring-rose-600/20",
    failed: "bg-rose-50 text-rose-700 ring-rose-600/20",
    inactive: "bg-slate-100 text-slate-600 ring-slate-500/20",
};

// A medicine is "low" once its stock drops to this many units or fewer —
// used only for the stock badge, not enforced by the backend.
const LOW_STOCK_THRESHOLD = 10;

const GENDER_LABELS = {
    male: "Nam",
    female: "Nữ",
    other: "Khác",
};

export function localDateInput(date = new Date()) {
    return toClinicClock(date).toISOString().slice(0, 10);
}

export function formatDate(date, options = {}) {
    if (!date) {
        return "—";
    }

    return new Intl.DateTimeFormat("vi-VN", {
        dateStyle: "long",
        timeZone: CLINIC_TIMEZONE,
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

    return new Intl.DateTimeFormat("vi-VN", { timeZone: "UTC" }).format(
        new Date(Date.UTC(year, month - 1, day)),
    );
}

export function formatTime(date) {
    if (!date) {
        return "—";
    }

    return new Intl.DateTimeFormat("vi-VN", {
        hour: "2-digit",
        minute: "2-digit",
        timeZone: CLINIC_TIMEZONE,
    }).format(new Date(date));
}

export function formatNumber(value) {
    return new Intl.NumberFormat("vi-VN").format(value ?? 0);
}

export function formatCurrency(value) {
    return new Intl.NumberFormat("vi-VN", {
        style: "currency",
        currency: "VND",
    }).format(value ?? 0);
}

export function formatDoctorName(name) {
    const value = String(name ?? "").trim();

    if (!value) {
        return "—";
    }

    return /^(?:BS\.|Bác sĩ)\s+/iu.test(value) ? value : `BS. ${value}`;
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

export function stockLabel(stock) {
    const value = Number(stock ?? 0);

    if (value <= 0) {
        return "Hết hàng";
    }

    if (value <= LOW_STOCK_THRESHOLD) {
        return `Sắp hết (${formatNumber(value)})`;
    }

    return `Còn hàng (${formatNumber(value)})`;
}

export function stockClasses(stock) {
    const value = Number(stock ?? 0);

    if (value <= 0) {
        return "bg-rose-50 text-rose-700 ring-rose-600/20";
    }

    if (value <= LOW_STOCK_THRESHOLD) {
        return "bg-amber-50 text-amber-700 ring-amber-600/20";
    }

    return "bg-emerald-50 text-emerald-700 ring-emerald-600/20";
}

export function localDateTimeInput(date = new Date()) {
    return toClinicClock(date).toISOString().slice(0, 16);
}

// Reads a <input type="datetime-local"> value as clinic time, not browser time.
export function fromLocalDateTimeInput(value) {
    return fromClinicClock(new Date(value + "Z"));
}

export { CLINIC_TIMEZONE, toClinicClock, fromClinicClock };
