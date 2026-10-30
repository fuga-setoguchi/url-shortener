<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // 互換性を壊す変更は v2 として追加する（方式設計書 3.2）
        apiPrefix: 'api/v1',
        // リダイレクトはステートレスなため、セッション・Cookie・CSRFを扱う web グループを適用しない
        then: function (): void {
            Route::group([], __DIR__.'/../routes/web.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        // API全体のレート制限（制限値は AppServiceProvider で定義）
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // /api/* のエラーは Accept ヘッダーに関わらず常にJSONで返す
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
