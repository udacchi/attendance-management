<?php
declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use Tests\Feature\Acceptance\_helpers\FeatureTestCase;
use Tests\Feature\Acceptance\_helpers\Routes;
use App\Models\User;

final class Case03_AdminLoginValidationTest extends FeatureTestCase
{
    use Routes;

    /** ①メールアドレス未入力 */
    public function test_email_required(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $res = $this->post($this->ROUTE_ADMIN_LOGIN, [
            'email'    => '',
            'password' => 'password123',
        ]);

        $res->assertSessionHasErrors(['email']);
        $this->assertStringContainsString($this->MSG_EMAIL_REQUIRED, session('errors')->first('email'));
    }

    /** ②パスワード未入力 */
    public function test_password_required(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $res = $this->post($this->ROUTE_ADMIN_LOGIN, [
            'email'    => 'admin@example.com',
            'password' => '',
        ]);

        $res->assertSessionHasErrors(['password']);
        $this->assertStringContainsString($this->MSG_PASSWORD_REQUIRED, session('errors')->first('password'));
    }

    /** ③登録内容と一致しない場合 */
    public function test_login_invalid(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $res = $this->post($this->ROUTE_ADMIN_LOGIN, [
            'email'    => 'wrong@example.com',
            'password' => 'password123',
        ]);

        $res->assertSessionHasErrors(['email']);
        $this->assertStringContainsString($this->MSG_LOGIN_NOT_FOUND, session('errors')->first('email'));
    }
}
