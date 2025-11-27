<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Case01_UserRegisterTest extends TestCase
{
    use RefreshDatabase;

    private $ROUTE_REGISTER = '/register';

    /** お名前未入力 */
    public function test_name_required()
    {
        $r = $this->post($this->ROUTE_REGISTER, [
            'name' => '',
            'email' => 'a@ex.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        // 文字列ではなく「キー存在」で検証
        $r->assertSessionHasErrors(['name']);
    }

    /** メール未入力 */
    public function test_email_required()
    {
        $r = $this->post($this->ROUTE_REGISTER, [
            'name' => '山田',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        $r->assertSessionHasErrors(['email']);
    }

    /** パスワード8未満 */
    public function test_password_min_8()
    {
        $r = $this->post($this->ROUTE_REGISTER, [
            'name' => '田中',
            'email' => 'b@ex.com',
            'password' => 'short',
            'password_confirmation' => 'short'
        ]);

        $r->assertSessionHasErrors(['password']);
    }

    /** パスワード一致しない */
    public function test_password_confirmation_mismatch()
    {
        $r = $this->post($this->ROUTE_REGISTER, [
            'name' => '鈴木',
            'email' => 'c@ex.com',
            'password' => 'password123',
            'password_confirmation' => 'DIFF'
        ]);

        $r->assertSessionHasErrors(['password']);
    }

    /** パスワード未入力 */
    public function test_password_required()
    {
        $r = $this->post($this->ROUTE_REGISTER, [
            'name' => '佐藤',
            'email' => 'd@ex.com',
            'password' => '',
            'password_confirmation' => ''
        ]);

        $r->assertSessionHasErrors(['password']);
    }

    /** 入力されていたら保存される */
    public function test_register_success_persisted()
    {
        $this->post($this->ROUTE_REGISTER, [
            'name' => 'テスト太郎',
            'email' => 'success@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'success@example.com'
        ]);
    }
}
