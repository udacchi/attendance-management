<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    // 管理者ログイン画面（GET）
    public function show()
    {
        return view('admin.login'); // 既存の admin/login.blade.php に合わせてください
    }

    // 管理者ログイン送信（POST）
    public function store(AdminLoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $remember    = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            // role チェック：一般ユーザーは拒否
            if (Auth::user()->role !== 'admin') {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'ログイン情報が登録されていません',
                ])->onlyInput('email');
            }

            return redirect()->intended(route('admin.attendance.list'));
        }

        // 認証失敗
        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません',
        ])->onlyInput('email');
    }

    // 管理者ログアウト（POST）
    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
