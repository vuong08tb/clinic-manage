import { getPayments } from "./payment-api";

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
