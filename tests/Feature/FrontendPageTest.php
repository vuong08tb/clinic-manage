<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendPageTest extends TestCase
{
    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
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
            ->assertSee('Tổng quan công việc theo quyền truy cập của bạn.');
    }
}
