<?php

declare(strict_types=1);

use App\Rules\SafeRedirectUrl;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    config(['shorturl.base_url' => 'http://localhost:8000']);
});

/**
 * SafeRedirectUrl だけで検証し、エラーメッセージ（なければ null）を返す。
 */
function safeRedirectUrlError(mixed $value): ?string
{
    $validator = Validator::make(['url' => $value], ['url' => [new SafeRedirectUrl]]);

    return $validator->errors()->first('url') ?: null;
}

it('安全なURLは通す', function (string $url): void {
    expect(safeRedirectUrlError($url))->toBeNull();
})->with([
    'https' => ['https://example.com/path?q=1#top'],
    'http' => ['http://example.com'],
    '自サービスと別のポート' => ['http://localhost:5173/'],
]);

it('API-01-T17 制御文字・空白を含むURLを拒否する', function (string $url): void {
    expect(safeRedirectUrlError($url))->toBe('空白や改行を含むURLは登録できません。');
})->with([
    'CR/LF' => ["https://example.com/\r\nSet-Cookie: a=b"],
    'LFのみ' => ["https://example.com/\nX"],
    'タブ' => ["https://example.com/\tpath"],
    '空白' => ['https://example.com/a b'],
    'NUL' => ["https://example.com/\0"],
    'DEL' => ["https://example.com/\x7F"],
]);

it('API-01-T15 認証情報を含むURLを拒否する', function (string $url): void {
    expect(safeRedirectUrlError($url))->toBe('ユーザー名やパスワードを含むURLは登録できません。');
})->with([
    'user:pass' => ['https://user:pass@example.com'],
    'userのみ（ホストの偽装）' => ['https://google.com@evil.example'],
]);

it('API-01-T16 自サービスのURLを拒否する', function (string $url): void {
    expect(safeRedirectUrlError($url))->toBe('このサービスの短いURLは登録できません。');
})->with([
    '同じホスト・ポート' => ['http://localhost:8000/abcdefg'],
    'ホストの大文字小文字違い' => ['http://LocalHost:8000/abcdefg'],
    'スキームが違っても同じホスト・ポート' => ['https://localhost:8000/abcdefg'],
]);

it('API-01-T16 SHORT_URL_BASE が既定ポートの場合、ポート省略のURLも自サービスとみなす', function (): void {
    config(['shorturl.base_url' => 'https://sho.rt']);

    expect(safeRedirectUrlError('https://sho.rt/abcdefg'))->toBe('このサービスの短いURLは登録できません。')
        ->and(safeRedirectUrlError('https://sho.rt:443/abcdefg'))->toBe('このサービスの短いURLは登録できません。')
        ->and(safeRedirectUrlError('http://sho.rt/abcdefg'))->toBeNull();
});

it('文字列以外は対象外とする（型の検証は string ルールに任せる）', function (): void {
    expect(safeRedirectUrlError(['https://user:pass@example.com']))->toBeNull();
});
