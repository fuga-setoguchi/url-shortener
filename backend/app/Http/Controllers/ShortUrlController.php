<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateShortUrl;
use App\Http\Requests\IndexShortUrlRequest;
use App\Http\Requests\StoreShortUrlRequest;
use App\Http\Resources\ShortUrlResource;
use App\Models\ShortUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShortUrlController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * 短縮URL一覧取得（API-02）
     */
    public function index(IndexShortUrlRequest $request): AnonymousResourceCollection
    {
        $shortUrls = ShortUrl::query()
            ->withCount('clicks')
            // 作成日時が同じ場合もIDの降順で並びを安定させる
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return ShortUrlResource::collection($shortUrls);
    }

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
