<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
// （任意）ログイン後のリダイレクトを固定したい場合
use Laravel\Fortify\Contracts\LoginResponse;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 任意：ログイン後に勤怠打刻画面へ飛ばす例
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
        // ここで Fortify にビューを結びつけます（あなたの Blade 名に合わせる）
        Fortify::loginView(fn() => view('auth.login'));
        Fortify::registerView(fn() => view('auth.register'));
        Fortify::verifyEmailView(fn() => view('auth.verify-email'));

        // レート制限（お好みで）
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(($request->email ?? 'guest') . '|' . $request->ip());
        });
        RateLimiter::for('two-factor', fn() => Limit::perMinute(5));
    }
}
