<?php

declare(strict_types=1);

use App\Http\Controllers\RedirectController;
use Illuminate\Support\Facades\Route;

// ブラウザが直接アクセスするルート。セッション・Cookieを使わないため、
// web ミドルウェアグループを適用せずに登録する（bootstrap/app.php、方式設計書 3.2）
Route::get('/{code}', RedirectController::class)
    ->where('code', '[A-Za-z0-9]{7}');
