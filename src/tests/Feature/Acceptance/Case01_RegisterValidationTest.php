<?php
declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use Tests\Feature\Acceptance\_helpers\Routes;

final class Case01_RegisterValidationTest extends FeatureTestCase
{
    use Routes;

    /** ①名前未入力 */
    public function test_name_required(): void
    {
        $res = $this->post($this->ROUTE_REGISTER, [
            'name'                  => '',
            'email'                 => 'u@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertSessionHasErrors(['name']);
        $this->assertStringContainsString($this->MSG_NAME_REQUIRED, session('errors')->first('name'));
    }

    /** ②メール未入力 */
    public function test_email_required(): void
    {
        $res = $this->post($this->ROUTE_REGISTER, [
            'name'                  => '山田太郎',
            'email'                 => '',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertSessionHasErrors(['email']);
        $this->assertStringContainsString($this->MSG_EMAIL_REQUIRED, session('errors')->first('email'));
    }

    /** ③パスワード8文字未満 */
    public function test_password_min_8(): void
    {
        $res = $this->post($this->ROUTE_REGISTER, [
            'name'                  => '山田太郎',
            'email'                 => 'u@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $res->assertSessionHasErrors(['password']);
        $this->assertStringContainsString($this->MSG_PASSWORD_MIN, session('errors')->first('password'));
    }

    /** ④パスワード不一致 */
    public function test_password_confirmation_must_match(): void
    {
        $res = $this->post($this->ROUTE_REGISTER, [
            'name'                  => '山田太郎',
            'email'                 => 'u@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'different',
        ]);

        $res->assertSessionHasErrors(['password']);
        $this->assertStringContainsString($this->MSG_PASSWORD_CONFIRM, session('errors')->first('password'));
    }

    /** ⑤パスワード未入力 */
    public function test_password_required(): void
    {
        $res = $this->post($this->ROUTE_REGISTER, [
            'name'                  => '山田太郎',
            'email'                 => 'u@example.com',
            'password'              => '',
            'password_confirmation' => '',
        ]);

        $res->assertSessionHasErrors(['password']);
        $this->assertStringContainsString($this->MSG_PASSWORD_REQUIRED, session('errors')->first('password'));
    }

    /** ⑥登録成功 */
    public function test_register_success(): void
    {
        $payload = [
            'name'                  => '山田太郎',
            'email'                 => 'u@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ];

        $res = $this->post($this->ROUTE_REGISTER, $payload);

        // Fortify 既定の挙動（メール認証ONなら /email/verify 等へリダイレクト）
        $res->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'u@example.com',
            'name'  => '山田太郎',
        ]);
    }
}
