<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\RecordClick;
use App\Models\ShortUrl;
use Illuminate\Http\RedirectResponse;

class RedirectController extends Controller
{
    /**
     * 短いURLから元のURLへ転送する（API-03）
     */
    public function __invoke(string $code): RedirectResponse
    {
        $shortUrl = ShortUrl::where('code', $code)->firstOrFail();

        RecordClick::dispatch($shortUrl->id, now());

        // 301 はブラウザにキャッシュされ、2回目以降のクリックを記録できないため 302 を使う
        return new RedirectResponse($shortUrl->original_url, RedirectResponse::HTTP_FOUND);
    }
}
