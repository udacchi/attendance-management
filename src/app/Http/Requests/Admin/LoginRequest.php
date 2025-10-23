<?php

namespace App\Http\Requests\admin;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 仕様に合わせて「未入力」メッセージを出す必須チェックのみ
        return [
            'email'    => ['required'],
            'password' => ['required'],
        ];
    }

    public function messages(): array
    {
        // 要件の文言どおり
        return [
            'email.required'    => 'メールアドレスを入力してください',
            'password.required' => 'パスワードを入力してください',
        ];
    }
}
