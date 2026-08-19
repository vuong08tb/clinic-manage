import Alpine from "alpinejs";
import { loginPage } from "./features/auth/login";
import { dashboardPage } from "./features/dashboard";
import { patientIndexPage } from "./features/patients";
import { createAuthStore } from "./stores/auth-store";
import { createUiStore } from "./stores/ui-store";
import { appointmentIndexPage } from "./features/appointments";
import { examinationIndexPage } from "./features/examinations";

window.Alpine = Alpine;

document.addEventListener("alpine:init", () => {
    Alpine.store("auth", createAuthStore());
    Alpine.store("ui", createUiStore());

    Alpine.data("loginPage", loginPage);
    Alpine.data("dashboardPage", dashboardPage);
    Alpine.data("patientIndexPage", patientIndexPage);
    Alpine.data("appointmentIndexPage", appointmentIndexPage);
    Alpine.data("examinationIndexPage", examinationIndexPage);
});

Alpine.start();
