<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Config;

/**
 * 転送先として安全なURLかを検証する（方式設計書 6.1）。
 * スキーム（http/https）と形式は Laravel の url ルールで検証済みである前提とする。
 */
class SafeRedirectUrl implements ValidationRule
{
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // Location ヘッダーへの改行混入を防ぐ
        if (preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
            $fail('validation.safe_redirect_url.whitespace')->translate();

            return;
        }

        $url = parse_url($value);
        if ($url === false) {
            return;
        }

        // https://google.com@evil.example のような転送先の偽装を防ぐ
        if (isset($url['user']) || isset($url['pass'])) {
            $fail('validation.safe_redirect_url.credentials')->translate();

            return;
        }

        if ($this->isOwnHost($url)) {
            $fail('validation.safe_redirect_url.own_host')->translate();
        }
    }

    /**
     * 短いURLと同じホスト・ポートか（短縮URLの多重化・転送ループを防ぐ）。
     *
     * @param  array{scheme?: string, host?: string, port?: int}  $url
     */
    private function isOwnHost(array $url): bool
    {
        $base = parse_url(Config::string('shorturl.base_url'));
        if ($base === false) {
            return false;
        }

        return $this->origin($url) !== null && $this->origin($url) === $this->origin($base);
    }

    /**
     * @param  array{scheme?: string, host?: string, port?: int}  $url
     */
    private function origin(array $url): ?string
    {
        if (! isset($url['host'])) {
            return null;
        }

        $scheme = strtolower($url['scheme'] ?? 'http');
        $port = $url['port'] ?? self::DEFAULT_PORTS[$scheme] ?? null;

        return strtolower($url['host']).':'.$port;
    }
}
