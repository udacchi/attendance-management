<?php

return [
    // よく使うルールの日本語
    'required' => ':attributeを入力してください',
    'email'    => ':attributeの形式が正しくありません',
    'min'      => [
        'string' => ':attributeは:min文字以上で入力してください',
    ],
    'max'      => [
        'string' => ':attributeは:max文字以下で入力してください',
    ],
    'confirmed' => ':attributeと一致しません',
    'unique'    => ':attributeは既に使用されています',
    'string'    => ':attributeは文字列で入力してください',
    'numeric'   => ':attributeは数値で入力してください',
    'regex'     => ':attributeの形式が正しくありません',

    // 属性名の日本語化（ここが最後に来る＝上書きされない）
    'attributes' => [
        'email'    => 'メールアドレス',
        'password' => 'パスワード',
        'name'     => 'お名前',
    ],
];
