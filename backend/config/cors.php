<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | 画面は Vite のプロキシ経由で同一オリジンから API を呼び出すため、
    | 他のオリジンからのアクセスは一切許可しない（方式設計書 6.5）。
    | Laravel 既定の allowed_origins => ['*']（全オリジン許可）を残さない。
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => [],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
