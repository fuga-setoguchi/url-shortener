<?php

declare(strict_types=1);

// 本サービスで使う検証ルールの日本語メッセージ。未定義のものは英語（フォールバック）で表示される
return [
    'integer' => ':attributeは整数で指定してください。',
    'max' => [
        'string' => ':attributeは:max文字以内で指定してください。',
    ],
    'min' => [
        'numeric' => ':attributeは:min以上で指定してください。',
    ],
    'required' => ':attributeを入力してください。',
    'string' => ':attributeは文字列で指定してください。',
    'url' => ':attributeはhttpまたはhttpsで始まるURLを指定してください。',

    'safe_redirect_url' => [
        'whitespace' => '空白や改行を含むURLは登録できません。',
        'credentials' => 'ユーザー名やパスワードを含むURLは登録できません。',
        'own_host' => 'このサービスの短いURLは登録できません。',
    ],

    'attributes' => [
        'original_url' => '元のURL',
        'page' => 'ページ番号',
    ],
];
