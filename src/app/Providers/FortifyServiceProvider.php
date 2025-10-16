<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

// Fortify Actions
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LogoutResponse as CustomLogoutResponse;

// ★ 追加
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ログイン後に勤怠打刻画面へリダイレクト
        $this->app->singleton(LoginResponse::class, function () {
            return new class implements LoginResponse {
                public function toResponse($request)
                {
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

        // ★ 未認証ユーザーをログイン不可にする
        Fortify::authenticateUsing(function (Request $request) {
            
            $user = User::where('email', $request->input('email'))->first();

            if (! $user || ! Hash::check($request->input('password'), $user->password)) {
                return null; // 通常の認証失敗
            }

            if (! $user->hasVerifiedEmail()) {
                try {
                    $user->sendEmailVerificationNotification();
                } catch (\Throwable $e) {
                }

                throw ValidationException::withMessages([
                    Fortify::username() =>
                    'メールアドレスの確認が完了していません。受信トレイをご確認ください。（認証メールを再送しました）',
                ]);
            }

            return $user; // 認証OK
        });

        // ★ 契約 ↔ 具象クラスの紐付け
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
