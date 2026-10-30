<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateShortUrl;
use App\Http\Requests\StoreShortUrlRequest;
use App\Http\Resources\ShortUrlResource;
use Illuminate\Http\JsonResponse;

class ShortUrlController extends Controller
{
    /**
     * 短縮URL登録（API-01）
     */
    public function store(StoreShortUrlRequest $request, CreateShortUrl $createShortUrl): JsonResponse
    {
        $shortUrl = $createShortUrl->handle($request->originalUrl());
        $shortUrl->loadCount('clicks');

        return (new ShortUrlResource($shortUrl))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
