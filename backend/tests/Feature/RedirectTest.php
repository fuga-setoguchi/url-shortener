<?php

declare(strict_types=1);

use App\Jobs\RecordClick;
use App\Models\ShortUrl;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

it('API-03-T01 登録済みのコードにアクセスすると、元のURLへ302で転送する', function (): void {
    ShortUrl::factory()->create(['code' => 'aB3xK9q', 'original_url' => 'https://example.com/path?q=1']);

    $this->get('/aB3xK9q')
        ->assertStatus(302)
        ->assertHeader('Location', 'https://example.com/path?q=1');
});

it('API-03-T02 アクセス時刻でクリック記録ジョブを1回投入する', function (): void {
    $this->freezeTime();
    $shortUrl = ShortUrl::factory()->create(['code' => 'aB3xK9q']);

    $this->get('/aB3xK9q')->assertStatus(302);

    Queue::assertPushed(RecordClick::class, 1);
    Queue::assertPushed(
        RecordClick::class,
        fn (RecordClick $job): bool => $job->shortUrlId === $shortUrl->id && $job->clickedAt->equalTo(now()),
    );
});

it('API-03-T03 セッションを使わない（Set-Cookie ヘッダーを返さない）', function (): void {
    ShortUrl::factory()->create(['code' => 'aB3xK9q']);

    $this->get('/aB3xK9q')
        ->assertStatus(302)
        ->assertHeaderMissing('Set-Cookie');
});

it('API-03-T04 未登録のコードは404を返し、ジョブを投入しない', function (): void {
    $this->get('/Zz9Zz9Z')->assertNotFound();

    Queue::assertNothingPushed();
});

it('API-03-T05 形式が不正なコードは404を返す', function (string $path): void {
    ShortUrl::factory()->create(['code' => 'aB3xK9q']);

    $this->get($path)->assertNotFound();

    Queue::assertNothingPushed();
})->with([
    '6文字' => ['/aB3xK9'],
    '8文字' => ['/aB3xK9qq'],
    '記号を含む' => ['/aB3-K9q'],
]);

it('API-03-T06 大小文字を区別する（aB3xK9q と AB3XK9Q は別のコード）', function (): void {
    ShortUrl::factory()->create(['code' => 'aB3xK9q']);

    $this->get('/AB3XK9Q')->assertNotFound();

    Queue::assertNothingPushed();
});

it('API-03-T07 ヘルスチェック（/up）はリダイレクトのルートと衝突しない', function (): void {
    $this->get('/up')->assertOk();
});

it('API-03-T08 セキュリティ関連のヘッダーを付ける', function (): void {
    ShortUrl::factory()->create(['code' => 'aB3xK9q']);

    $this->get('/aB3xK9q')
        ->assertStatus(302)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});
