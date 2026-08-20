export function emptyPrescriptionForm() {
    return {
        examination_id: null,
        examination_label: "",
        notes: "",
        items: [],
    };
}

export function emptyItemDraft() {
    return {
        medicine_id: null,
        medicine_label: "",
        unit: "",
        stock: 0,
        quantity: 1,
        dosage: "",
        usage_instruction: "",
    };
}

let draftKeySeq = 0;

export function nextDraftKey() {
    draftKeySeq += 1;

    return `draft-${draftKeySeq}`;
}

export function createPayload(form) {
    return {
        examination_id: form.examination_id,
        notes: form.notes.trim() || null,
        items: form.items.map((item) => ({
            medicine_id: item.medicine_id,
            quantity: Number(item.quantity),
            dosage: item.dosage.trim(),
            usage_instruction: item.usage_instruction.trim() || null,
        })),
    };
}

export function addItemPayload(item) {
    return {
        medicine_id: item.medicine_id,
        quantity: Number(item.quantity),
        dosage: item.dosage.trim(),
        usage_instruction: item.usage_instruction.trim() || null,
    };
}

export function updateItemPayload(item) {
    return {
        quantity: Number(item.quantity),
        dosage: item.dosage.trim(),
        usage_instruction: item.usage_instruction.trim() || null,
    };
}
