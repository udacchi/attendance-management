<?php
declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use Tests\Feature\Acceptance\_helpers\Routes;
use App\Models\User;

final class Case02_LoginValidationTest extends FeatureTestCase
{
    use Routes;

    /** ①メールアドレス未入力 */
    public function test_email_required(): void
    {
        // 事前にユーザー作成
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $res = $this->post($this->ROUTE_LOGIN, [
            'email'    => '',
            'password' => 'password123',
        ]);

        $res->assertSessionHasErrors(['email']);
        $this->assertStringContainsString(
            $this->MSG_EMAIL_REQUIRED,
            session('errors')->first('email')
        );
    }

    /** ②パスワード未入力 */
    public function test_password_required(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $res = $this->post($this->ROUTE_LOGIN, [
            'email'    => 'user@example.com',
            'password' => '',
        ]);

        $res->assertSessionHasErrors(['password']);
        $this->assertStringContainsString(
            $this->MSG_PASSWORD_REQUIRED,
            session('errors')->first('password')
        );
    }

    /** ③登録情報と一致しない（メールアドレス間違い） */
    public function test_login_invalid_email(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        $res = $this->post($this->ROUTE_LOGIN, [
            'email'    => 'wrong@example.com',
            'password' => 'password123',
        ]);

        $res->assertSessionHasErrors(['email']);   // Fortifyは email にエラーを割り当てる
        $this->assertStringContainsString(
            $this->MSG_LOGIN_NOT_FOUND,
            session('errors')->first('email')
        );
    }
}
