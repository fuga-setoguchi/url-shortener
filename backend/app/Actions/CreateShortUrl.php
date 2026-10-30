<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ShortUrl;
use App\Support\ShortCodeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * 短縮コードを発行して短縮URLを保存する。
 */
class CreateShortUrl
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly ShortCodeGenerator $generator,
    ) {}

    /**
     * @throws UniqueConstraintViolationException コードの重複が上限回数続いた場合
     */
    public function handle(string $originalUrl): ShortUrl
    {
        $attempt = 1;

        while (true) {
            try {
                // 事前の存在確認は競合を防げないため、DBの一意制約で重複を検知する
                return ShortUrl::create([
                    'code' => $this->generator->generate(),
                    'original_url' => $originalUrl,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }

                $attempt++;
            }
        }
    }
}
