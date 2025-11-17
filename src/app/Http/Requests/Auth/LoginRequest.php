<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    // 無名バッグのまま（Blade変更不要）
    protected $errorBag = 'default';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            // 汎用（後述のキー素通り対策）
            'required'    => ':attribute を入力してください。',
            'email'       => ':attribute の形式が正しくありません。',
            'min.string'  => ':attribute は :min 文字以上で入力してください。',

            // 個別上書き（任意）
            'email.required'    => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',
        ];
    }

    public function attributes(): array
    {
        return [
            'email'    => 'メールアドレス',
            'password' => 'パスワード',
        ];
    }
}
