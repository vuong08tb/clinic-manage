import { apiRequest } from "../../core/api-client";

export function getPayments(filters) {
    const params = new URLSearchParams({
        page: String(filters.page ?? 1),
        per_page: String(filters.per_page ?? 50),
    });

    if (filters.invoice_id) {
        params.set("invoice_id", String(filters.invoice_id));
    }

    if (filters.provider_order_id) {
        params.set("provider_order_id", filters.provider_order_id);
    }

    return apiRequest(`/payments?${params.toString()}`);
}

export function createPayment(invoiceId, payload) {
    return apiRequest(`/invoices/${invoiceId}/payments`, {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function capturePayment(paymentId) {
    return apiRequest(`/payments/${paymentId}/capture`, {
        method: "POST",
    });
}

export function getPayPalClientToken() {
    return apiRequest("/payments/paypal/client-token");
}
