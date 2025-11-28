<?php

namespace Tests\Feature\Acceptance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Acceptance\Support\AttTestHelpers;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use App\Models\User;

class Case16_EmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase, AttTestHelpers;

    /** 会員登録後、認証メールが送信される */
    public function test_verification_mail_sent_after_register()
    {
        Notification::fake();
        $this->post($this->ROUTE_REGISTER, [
            'name' => '太郎',
            'email' => 'verify@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $user = User::where('email', 'verify@example.com')->first();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /** 誘導画面から「認証はこちらから」で認証サイトへ（文言存在確認） */
    public function test_verify_link_page_navigates()
    {
        $user = \App\Models\User::factory()->unverified()->create();

        // ログイン（webガード）
        $this->actingAs($user, 'web');

        // Fortify の notice ルート名を使うのが堅い（= GET /email/verify）
        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertViewIs('auth.verify-email')
            ->assertSeeText('メール認証');
    }


    /** メール認証完了で勤怠登録画面へ */
    public function test_email_verified_redirects_to_stamp()
    {
        $this->post($this->ROUTE_REGISTER, [
            'name' => '次郎',
            'email' => 'v2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $user = User::where('email', 'v2@example.com')->first();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user, 'web');

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        $this->get($url)->assertRedirect($this->ROUTE_STAMP);
    }
}
