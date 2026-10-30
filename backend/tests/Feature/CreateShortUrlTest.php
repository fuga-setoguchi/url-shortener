<?php

declare(strict_types=1);

use App\Models\ShortUrl;
use Illuminate\Support\Str;

const CREATE_SHORT_URL = '/api/v1/short-urls';

beforeEach(function (): void {
    config(['shorturl.base_url' => 'http://localhost:8000']);
});

it('API-01-T01 URLを登録すると201で短縮URLを返し、1件保存する', function (): void {
    $this->freezeTime();

    $response = $this->postJson(CREATE_SHORT_URL, ['original_url' => 'https://example.com/path?q=1']);

    $response->assertCreated()
        ->assertExactJsonStructure(['data' => ['code', 'short_url', 'original_url', 'clicks_count', 'created_at']])
        ->assertJsonPath('data.original_url', 'https://example.com/path?q=1')
        ->assertJsonPath('data.clicks_count', 0)
        ->assertJsonPath('data.created_at', now()->toIso8601String());

    $code = $response->json('data.code');
    expect($code)->toMatch('/^[A-Za-z0-9]{7}$/')
        ->and($response->json('data.short_url'))->toBe("http://localhost:8000/{$code}");

    $this->assertDatabaseCount('short_urls', 1);
    $this->assertDatabaseHas('short_urls', ['code' => $code, 'original_url' => 'https://example.com/path?q=1']);
});

it('API-01-T02 http のURLを登録できる', function (): void {
    $this->postJson(CREATE_SHORT_URL, ['original_url' => 'http://example.com'])->assertCreated();
});

it('API-01-T03 ちょうど2048文字のURLを登録できる', function (): void {
    $url = 'https://example.com/'.str_repeat('a', 2048 - strlen('https://example.com/'));
    expect(strlen($url))->toBe(2048);

    $this->postJson(CREATE_SHORT_URL, ['original_url' => $url])->assertCreated();
});

it('API-01-T04 同じURLを2回登録すると、異なるコードを発行する', function (): void {
    $first = $this->postJson(CREATE_SHORT_URL, ['original_url' => 'https://example.com'])->assertCreated();
    $second = $this->postJson(CREATE_SHORT_URL, ['original_url' => 'https://example.com'])->assertCreated();

    expect($first->json('data.code'))->not->toBe($second->json('data.code'));
});

it('API-01-T05 original_url がないと422を返し、保存しない', function (): void {
    $this->postJson(CREATE_SHORT_URL, [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['original_url']);

    $this->assertDatabaseCount('short_urls', 0);
});

it('不正な original_url は422を返し、理由を示すメッセージを返して保存しない', function (mixed $originalUrl, string $message): void {
    $this->postJson(CREATE_SHORT_URL, ['original_url' => $originalUrl])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['original_url' => $message]);

    $this->assertDatabaseCount('short_urls', 0);
})->with([
    'API-01-T06 空文字' => ['', '元のURLを入力してください。'],
    'API-01-T07 URL形式でない' => ['not-a-url', '元のURLはhttpまたはhttpsで始まるURLを指定してください。'],
    'API-01-T08 javascript スキーム' => ['javascript:alert(1)', '元のURLはhttpまたはhttpsで始まるURLを指定してください。'],
    'API-01-T09 ftp スキーム' => ['ftp://example.com', '元のURLはhttpまたはhttpsで始まるURLを指定してください。'],
    'API-01-T10 2049文字' => ['https://example.com/'.str_repeat('a', 2049 - strlen('https://example.com/')), '元のURLは2048文字以内で指定してください。'],
    'API-01-T11 文字列以外（配列）' => [['https://example.com'], '元のURLは文字列で指定してください。'],
    // 以下の2種類は Laravel の url ルールを通過するため、SafeRedirectUrl で拒否されることを確認する
    'API-01-T15 認証情報付き（user:pass）' => ['https://user:pass@example.com', 'ユーザー名やパスワードを含むURLは登録できません。'],
    'API-01-T15 認証情報付き（ホストの偽装）' => ['https://google.com@evil.example', 'ユーザー名やパスワードを含むURLは登録できません。'],
    'API-01-T16 自サービスのURL' => ['http://localhost:8000/abcdefg', 'このサービスの短いURLは登録できません。'],
    'API-01-T16 自サービスのURL（大文字小文字違い）' => ['HTTP://LOCALHOST:8000/abcdefg', 'このサービスの短いURLは登録できません。'],
    // 制御文字・空白は url ルールが先に拒否する（SafeRedirectUrl 側の検査は SafeRedirectUrlTest で確認）
    'API-01-T17 CR/LF を含む' => ["https://example.com/\r\nSet-Cookie: a=b", '元のURLはhttpまたはhttpsで始まるURLを指定してください。'],
    'API-01-T17 タブを含む' => ["https://example.com/\tpath", '元のURLはhttpまたはhttpsで始まるURLを指定してください。'],
    'API-01-T17 空白を含む' => ['https://example.com/a b', '元のURLはhttpまたはhttpsで始まるURLを指定してください。'],
]);

it('API-01-T12 1分間に11回登録すると、11回目は429を返す', function (): void {
    for ($i = 1; $i <= 10; $i++) {
        $this->postJson(CREATE_SHORT_URL, ['original_url' => 'https://example.com'])->assertCreated();
    }

    $this->postJson(CREATE_SHORT_URL, ['original_url' => 'https://example.com'])->assertTooManyRequests();
});

it('API-01-T13 SHORT_URL_BASE を変更すると、そのドメインの短縮URLを返す', function (): void {
    config(['shorturl.base_url' => 'https://sho.rt']);

    $response = $this->postJson(CREATE_SHORT_URL, ['original_url' => 'https://example.com'])->assertCreated();

    expect($response->json('data.short_url'))->toBe('https://sho.rt/'.$response->json('data.code'));
});

it('API-01-T14 SHORT_URL_BASE が末尾スラッシュ付きでも / が重ならない', function (): void {
    config(['shorturl.base_url' => 'https://sho.rt/']);

    $response = $this->postJson(CREATE_SHORT_URL, ['original_url' => 'https://example.com'])->assertCreated();

    expect($response->json('data.short_url'))->toBe('https://sho.rt/'.$response->json('data.code'));
});

it('API-01-T16 自サービスと別のポートのURLは登録できる', function (): void {
    $this->postJson(CREATE_SHORT_URL, ['original_url' => 'http://localhost:5173/'])->assertCreated();
});

it('API-01-T18 code を送っても無視し、ランダムなコードを発行する', function (): void {
    $response = $this->postJson(CREATE_SHORT_URL, [
        'original_url' => 'https://example.com',
        'code' => 'aaaaaaa',
    ])->assertCreated();

    expect($response->json('data.code'))->not->toBe('aaaaaaa');
    expect(ShortUrl::where('code', 'aaaaaaa')->exists())->toBeFalse();
});

it('API-01-T19 セキュリティ関連のヘッダーを付ける', function (): void {
    $response = $this->postJson(CREATE_SHORT_URL, ['original_url' => 'https://example.com'])->assertCreated();

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('API-01-T20 Accept ヘッダーがなくても、検証エラーはJSONの422で返す', function (): void {
    $response = $this->post(CREATE_SHORT_URL, ['original_url' => 'not-a-url']);

    $response->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonValidationErrors(['original_url']);
});

it('レスポンスに内部ID（id）を含めない', function (): void {
    $response = $this->postJson(CREATE_SHORT_URL, ['original_url' => 'https://example.com'])->assertCreated();

    expect($response->json('data'))->not->toHaveKey('id');
    expect(Str::contains($response->getContent() ?: '', '"id"'))->toBeFalse();
});
