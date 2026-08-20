export function emptyInvoiceForm() {
    return {
        examination_id: null,
        examination_label: "",
        discount: "0",
    };
}

export function invoiceToForm(invoice) {
    return {
        examination_id: invoice.examination_id ?? null,
        examination_label:
            `${invoice.examination?.patient?.full_name ?? "—"} · BS. ${invoice.examination?.doctor?.user?.name ?? "—"}`,
        discount: String(invoice.discount ?? 0),
    };
}

export function createPayload(form) {
    return {
        examination_id: form.examination_id,
        discount: Number(form.discount) || 0,
    };
}

export function editPayload(form) {
    return {
        discount: Number(form.discount) || 0,
    };
}
