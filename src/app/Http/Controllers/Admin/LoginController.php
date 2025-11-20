<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LoginController extends Controller
{
    // 管理者ログイン画面（GET）
    public function show()
    {
        // すでにadminガードで入っていればダッシュボードへ
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.attendance.list'); // 実在する方に
        }
        return view('admin.login');
    }

    // 管理者ログイン送信（POST）
    public function store(LoginRequest $request)
    {
        $credentials = [
            'email'    => $request->input('email'),
            'password' => $request->input('password'),
            // ★ ここを、usersテーブルの実際の値に合わせる
            'role'     => 'admin',   // 例：role カラムに 'admin' が入っている場合
        ];

        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $today = Carbon::now(config('app.timezone', 'Asia/Tokyo'))->toDateString();

            return redirect()->intended(
                route('admin.attendance.list', ['date' => $today])
            );
        }

        // 認証失敗（メール/パスワード/roleが合わない）
        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',
        ])->onlyInput('email');
    }

    // 管理者ログアウト（POST）
    public function destroy(Request $request)
    {
        Auth::guard('admin')->logout();              // adminガードをログアウト
        $request->session()->invalidate();           // セッション無効化
        $request->session()->regenerateToken();      // CSRFトークン再生成
        return redirect()->route('admin.login');     // ログインへ戻す
    }
}
