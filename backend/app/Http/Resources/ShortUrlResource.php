<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ShortUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;

/**
 * @mixin ShortUrl
 */
class ShortUrlResource extends JsonResource
{
    /**
     * 内部ID（id）は返さない。識別子は code を使う。
     *
     * @return array{code: string, short_url: string, original_url: string, clicks_count: int, created_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'short_url' => rtrim(Config::string('shorturl.base_url'), '/').'/'.$this->code,
            'original_url' => $this->original_url,
            'clicks_count' => $this->clicks_count ?? 0,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
