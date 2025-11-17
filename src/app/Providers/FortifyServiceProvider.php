<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

// Fortify Actions
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LogoutResponse as CustomLogoutResponse;

// ★ 追加：FormRequest
use App\Http\Requests\Auth\LoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ★ ログイン後：未認証なら認証誘導、認証済みなら勤怠打刻へ
        $this->app->singleton(LoginResponse::class, function () {
            return new class implements LoginResponse {
                public function toResponse($request)
                {
                    $user = $request->user();
                    if ($user && !$user->hasVerifiedEmail()) {
                        return redirect()->route('verification.notice');
                    }
                    // 認証済みユーザーの遷移先（必要なら変更）
                    return redirect()->intended(route('attendance.stamp'));
                }
            };
        });

        // 登録直後はメール認証案内へ
        $this->app->singleton(RegisterResponse::class, function () {
            return new class implements RegisterResponse {
                public function toResponse($request)
                {
                    return redirect()->route('verification.notice');
                }
            };
        });
    }

    public function boot(): void
    {
        // Fortify のビュー定義
        Fortify::loginView(fn() => view('auth.login'));
        Fortify::registerView(fn() => view('auth.register'));
        Fortify::verifyEmailView(fn() => view('auth.verify-email'));

        // ★ 入力バリデーション（FormRequest）を認証前に差し込む
        Fortify::authenticateThrough(function (Request $request) {
            // 念のためロケール固定（翻訳に影響する場合の保険）
            app()->setLocale('ja');

            // ★ FormRequestを「使って」ルール/メッセージ/属性を取得し、こちらで確実に検証する
            /** @var \App\Http\Requests\Auth\LoginRequest $form */
            $form = app(LoginRequest::class);

            Validator::make(
                $request->all(),
                $form->rules(),
                method_exists($form, 'messages') ? $form->messages() : [],
                method_exists($form, 'attributes') ? $form->attributes() : []
            )->validate(); // ← 失敗時は自動でリダイレクト＆$errorsへ（無名バッグ）
            // 以降は Fortify 既定のパイプライン（2FA 使ってなければそのまま）
            return array_filter([
                \Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable::class,
                \Laravel\Fortify\Actions\AttemptToAuthenticate::class,
                \Laravel\Fortify\Actions\PrepareAuthenticatedSession::class,
            ]);
        });

        // ★ いままでの authenticateUsing で未認証を弾く処理は「削除」してください
        // Fortify::authenticateUsing(...) は不要

        // 契約 ↔ 具象クラスの紐付け
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $this->app->singleton(LogoutResponseContract::class, CustomLogoutResponse::class);

        // レート制限（任意）
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(($request->email ?? 'guest') . '|' . $request->ip());
        });
        RateLimiter::for('two-factor', fn() => Limit::perMinute(5));
    }
}
