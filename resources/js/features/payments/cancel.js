import { capturePayment, getPayments } from "./payment-api";

export function paymentCancelPage() {
    return {
        loading: true,
        payment: null,

        async init() {
            const user = await this.$store.auth.bootstrap();

            if (!user) {
                return;
            }

            const token = new URLSearchParams(window.location.search).get(
                "token",
            );

            if (!token) {
                this.loading = false;

                return;
            }

            try {
                const response = await getPayments({
                    provider_order_id: token,
                });

                this.payment = (response.data ?? [])[0] ?? null;

                if (this.payment?.status === "pending") {
                    try {
                        // PayPal has no cancel-order endpoint, so capture is the
                        // single reconciliation point: it comes back
                        // ORDER_NOT_APPROVED and the payment lands on "cancelled".
                        const settled = await capturePayment(this.payment.id);

                        this.payment = settled.data ?? this.payment;
                    } catch {
                        // Best-effort: the page's messaging doesn't depend on
                        // the server-side status, so a failed call (e.g. missing
                        // permission) shouldn't block rendering.
                    }
                }
            } catch {
                this.payment = null;
            } finally {
                this.loading = false;
            }
        },

        get invoiceUrl() {
            return this.payment?.invoice_id
                ? `/invoices?invoice_id=${this.payment.invoice_id}`
                : "/invoices";
        },
    };
}
