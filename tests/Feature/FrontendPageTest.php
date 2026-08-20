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
        ];

        foreach ($pages as $url => $modalTitleIds) {
            $response = $this->withoutVite()->get($url)->assertOk();

            foreach ($modalTitleIds as $modalTitleId) {
                $response->assertSee($modalTitleId, false);
            }
        }
    }

    public function test_removed_detail_routes_are_gone(): void
    {
        $this->withoutVite()->get('/patients/1')->assertNotFound();
        $this->withoutVite()->get('/examinations/create')->assertNotFound();
        $this->withoutVite()->get('/examinations/1')->assertNotFound();
    }
}
