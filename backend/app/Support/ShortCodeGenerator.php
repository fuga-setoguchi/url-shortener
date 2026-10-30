<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 推測されにくい短縮コード（英数字7文字）を生成する。
 */
class ShortCodeGenerator
{
    public const LENGTH = 7;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public function generate(): string
    {
        $maxIndex = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            // 暗号論的に安全な乱数を使う
            $code .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $code;
    }
}
