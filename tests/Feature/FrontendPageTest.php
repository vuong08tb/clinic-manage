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

    public function test_patient_detail_page_is_available(): void
    {
        $this->withoutVite()
            ->get('/patients/1')
            ->assertOk()
            ->assertSee('Chi tiết bệnh nhân');
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
}