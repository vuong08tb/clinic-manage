export function emptyMedicineForm() {
    return {
        code: "",
        name: "",
        unit: "",
        price: "",
        stock: 0,
        is_active: true,
    };
}

export function medicineToForm(medicine) {
    return {
        code: medicine.code ?? "",
        name: medicine.name ?? "",
        unit: medicine.unit ?? "",
        price: medicine.price ?? "",
        stock: medicine.stock ?? 0,
        is_active: Boolean(medicine.is_active),
    };
}

// stock is only sent on create — after that it is a read-only field here and
// changes only through the dedicated adjust-stock endpoint (see stockAdjustPayload).
export function createPayload(form) {
    return {
        code: form.code.trim(),
        name: form.name.trim(),
        unit: form.unit.trim(),
        price: Number(form.price),
        stock: Number(form.stock),
        is_active: form.is_active,
    };
}

export function updatePayload(form) {
    return {
        code: form.code.trim(),
        name: form.name.trim(),
        unit: form.unit.trim(),
        price: Number(form.price),
        is_active: form.is_active,
    };
}

export function emptyStockForm() {
    return {
        direction: "in",
        amount: "",
        note: "",
    };
}

export function stockAdjustPayload(form) {
    const amount = Math.abs(Number(form.amount));

    return {
        quantity: form.direction === "out" ? -amount : amount,
        note: form.note.trim() || null,
    };
}
