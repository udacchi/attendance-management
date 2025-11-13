<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered; // ← 追加

class RegisteredUserController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // ★ これがないと「確認メール」が送られません
        event(new Registered($user));

        // Fortify の「メール認証あり」運用ならログインして認証案内へ
        Auth::guard('web')->login($user);

        // 認証案内（/email/verify）へ遷移
        return redirect()->route('verification.notice');
    }
}
