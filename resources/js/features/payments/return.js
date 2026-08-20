import { ApiError } from "../../core/api-error";
import { capturePayment, getPayments } from "./payment-api";

export function paymentReturnPage() {
    return {
        // loading | success | failed | not_found | error
        status: "loading",
        message: "",
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
                this.status = "error";
                this.message = "Thiếu thông tin giao dịch từ PayPal.";

                return;
            }

            try {
                const listResponse = await getPayments({
                    provider_order_id: token,
                });
                const found = (listResponse.data ?? [])[0];

                if (!found) {
                    this.status = "not_found";
                    this.message = "Không tìm thấy thanh toán tương ứng.";

                    return;
                }

                if (found.status !== "pending") {
                    // Already captured (e.g. the user hit back/refresh on this page).
                    this.payment = found;
                    this.status = found.status === "completed" ? "success" : "failed";

                    return;
                }

                const captureResponse = await capturePayment(found.id);

                this.payment = captureResponse.data;
                this.status =
                    this.payment.status === "completed" ? "success" : "failed";
            } catch (error) {
                this.status = "error";
                this.message =
                    error instanceof ApiError
                        ? error.message
                        : "Đã xảy ra lỗi không mong muốn. Vui lòng thử lại.";
            }
        },

        get invoiceUrl() {
            return this.payment?.invoice_id
                ? `/invoices?invoice_id=${this.payment.invoice_id}`
                : "/invoices";
        },
    };
}
