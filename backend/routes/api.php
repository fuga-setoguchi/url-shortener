<?php

declare(strict_types=1);

use App\Http\Controllers\ShortUrlController;
use Illuminate\Support\Facades\Route;

// プレフィックス /api/v1 と API全体のレート制限は bootstrap/app.php で設定する
Route::get('/short-urls', [ShortUrlController::class, 'index']);

Route::post('/short-urls', [ShortUrlController::class, 'store'])
    ->middleware('throttle:create-short-url');
