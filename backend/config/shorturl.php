<?php

declare(strict_types=1);

return [

    /*
    | 短いURLのベースURL（例: http://localhost:8000）。
    | 開発者が管理する設定値のため、形式は検証しない（方式設計書 3.3）。
    */
    'base_url' => (string) env('SHORT_URL_BASE', env('APP_URL', 'http://localhost:8000')),

];
