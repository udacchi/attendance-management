<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;

// Fortify Actions
use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\ResetUserPassword;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 任意：ログイン後に勤怠打刻画面へリダイレクト
        $this->app->singleton(LoginResponse::class, function () {
            return new class implements LoginResponse {
                public function toResponse($request)
                {
                    return redirect()->intended(route('attendance.stamp'));
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

        // ★ 契約 ↔ 具象クラスの紐付け（必須）
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // レート制限（任意）
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(($request->email ?? 'guest') . '|' . $request->ip());
        });
        RateLimiter::for('two-factor', fn() => Limit::perMinute(5));
    }
}
