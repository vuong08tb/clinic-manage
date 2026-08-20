<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendPageTest extends TestCase
{
    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')
            ->assertRedirect('/dashboard');
    }

    public function test_login_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/login')
            ->assertOk()
            ->assertSee('Đăng nhập tài khoản');
    }

    public function test_dashboard_shell_is_available(): void
    {
        $this->withoutVite()
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(
                'Tổng quan công việc theo quyền truy cập của bạn.',
            );
    }

    public function test_patient_index_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/patients')
            ->assertOk()
            ->assertSee(
                'Quản lý hồ sơ và thông tin liên hệ của bệnh nhân.',
            );
    }

    public function test_appointment_index_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/appointments')
            ->assertOk()
            ->assertSee(
                'Quản lý lịch khám của bệnh nhân theo bác sĩ.',
            );
    }

    public function test_examination_index_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/examinations')
            ->assertOk()
            ->assertSee(
                'Hồ sơ khám bệnh được tạo từ lịch hẹn đã xác nhận.',
            );
    }

    public function test_prescription_index_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/prescriptions')
            ->assertOk()
            ->assertSee(
                'Toa thuốc được kê từ phiếu khám, quản lý thuốc trong toa và tồn kho.',
            );
    }

    public function test_medicine_index_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/medicines')
            ->assertOk()
            ->assertSee(
                'Danh mục thuốc, tồn kho và điều chỉnh nhập/xuất.',
            );
    }

    public function test_invoice_index_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/invoices')
            ->assertOk()
            ->assertSee(
                'Hóa đơn lập từ phiếu khám, theo dõi thanh toán qua PayPal.',
            );
    }

    public function test_payment_return_and_cancel_pages_are_available(): void
    {
        $this->withoutVite()->get('/payments/return')->assertOk();
        $this->withoutVite()->get('/payments/cancel')->assertOk();
    }

    public function test_specialty_index_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/specialties')
            ->assertOk()
            ->assertSee(
                'Danh mục chuyên khoa dùng để gán cho hồ sơ bác sĩ.',
            );
    }

    public function test_doctor_index_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/doctors')
            ->assertOk()
            ->assertSee(
                'Hồ sơ bác sĩ gắn với tài khoản người dùng và chuyên khoa.',
            );
    }

    public function test_user_index_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/users')
            ->assertOk()
            ->assertSee(
                'Quản lý tài khoản nhân viên và vai trò truy cập hệ thống.',
            );
    }

    /**
     * Add/edit/detail live in modals on the list page, so every feature renders
     * the shared modal shell instead of extra create/detail routes.
     */
    public function test_feature_pages_render_modal_shells(): void
    {
        $pages = [
            '/patients' => ['patient-form-modal-title', 'patient-detail-title'],
            '/appointments' => ['appointment-form-modal-title', 'appointment-detail-title'],
            '/examinations' => ['examination-form-modal-title', 'examination-detail-title'],
            '/prescriptions' => ['prescription-form-modal-title', 'prescription-detail-title'],
            '/medicines' => ['medicine-form-modal-title', 'medicine-detail-title', 'medicine-stock-modal-title'],
            '/invoices' => ['invoice-form-modal-title', 'invoice-detail-title'],
            '/specialties' => ['specialty-form-modal-title', 'specialty-detail-title'],
            '/doctors' => ['doctor-form-modal-title', 'doctor-detail-title'],
            '/users' => ['user-form-modal-title', 'user-detail-title'],
        ];

        foreach ($pages as $url => $modalTitleIds) {
            $response = $this->withoutVite()->get($url)->assertOk();

            foreach ($modalTitleIds as $modalTitleId) {
                $response->assertSee($modalTitleId, false);
            }
        }
    }

    /**
     * Alpine.data() already invokes a component's init(), so an extra x-init="init()"
     * in the markup runs it a second time. On /payments/return that meant two parallel
     * captures, where the second one failed and masked a successful payment.
     */
    public function test_pages_do_not_invoke_init_twice(): void
    {
        $pages = [
            '/login', '/dashboard', '/patients', '/appointments', '/examinations',
            '/prescriptions', '/medicines', '/invoices', '/payments/return',
            '/payments/cancel', '/specialties', '/doctors', '/users',
        ];

        foreach ($pages as $url) {
            $this->withoutVite()
                ->get($url)
                ->assertOk()
                ->assertDontSee('x-init="init()"', false);
        }
    }

    public function test_removed_detail_routes_are_gone(): void
    {
        $this->withoutVite()->get('/patients/1')->assertNotFound();
        $this->withoutVite()->get('/examinations/create')->assertNotFound();
        $this->withoutVite()->get('/examinations/1')->assertNotFound();
    }
}
