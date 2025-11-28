<?php

return [
    // よく使うルールの日本語
    'required' => ':attribute を入力してください。',
    'email'    => ':attribute の形式が正しくありません。',
    'min'      => [
        'string' => ':attribute は :min 文字以上で入力してください。',
    ],
    'max'      => [
        'string' => ':attribute は :max 文字以下で入力してください。',
    ],
    'confirmed' => ':attribute が確認用と一致しません。',
    'unique'    => ':attribute は既に使用されています。',
    'string'    => ':attribute は文字列で入力してください。',
    'numeric'   => ':attribute は数値で入力してください。',
    'regex'     => ':attribute の形式が正しくありません。',

    // 属性名の日本語化（ここが最後に来る＝上書きされない）
    'attributes' => [
        'email'    => 'メールアドレス',
        'password' => 'パスワード',
        'name'     => 'お名前',
    ],
];
