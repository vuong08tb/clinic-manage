import Alpine from "alpinejs";
import { loginPage } from "./features/auth/login";
import { dashboardPage } from "./features/dashboard";
import { patientIndexPage } from "./features/patients";
import { createAuthStore } from "./stores/auth-store";
import { createUiStore } from "./stores/ui-store";
import { appointmentIndexPage } from "./features/appointments";
import { examinationIndexPage } from "./features/examinations";
import { prescriptionIndexPage } from "./features/prescriptions";
import { medicineIndexPage } from "./features/medicines";
import { invoiceIndexPage } from "./features/invoices";
import { paymentReturnPage } from "./features/payments/return";
import { paymentCancelPage } from "./features/payments/cancel";
import { specialtyIndexPage } from "./features/specialties";
import { doctorIndexPage } from "./features/doctors";

window.Alpine = Alpine;

document.addEventListener("alpine:init", () => {
    Alpine.store("auth", createAuthStore());
    Alpine.store("ui", createUiStore());

    Alpine.data("loginPage", loginPage);
    Alpine.data("dashboardPage", dashboardPage);
    Alpine.data("patientIndexPage", patientIndexPage);
    Alpine.data("appointmentIndexPage", appointmentIndexPage);
    Alpine.data("examinationIndexPage", examinationIndexPage);
    Alpine.data("prescriptionIndexPage", prescriptionIndexPage);
    Alpine.data("medicineIndexPage", medicineIndexPage);
    Alpine.data("invoiceIndexPage", invoiceIndexPage);
    Alpine.data("paymentReturnPage", paymentReturnPage);
    Alpine.data("paymentCancelPage", paymentCancelPage);
    Alpine.data("specialtyIndexPage", specialtyIndexPage);
    Alpine.data("doctorIndexPage", doctorIndexPage);
});

Alpine.start();
