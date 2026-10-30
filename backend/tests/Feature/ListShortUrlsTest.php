<?php

declare(strict_types=1);

use App\Models\Click;
use App\Models\ShortUrl;
use Illuminate\Support\Facades\DB;

const LIST_SHORT_URLS = '/api/v1/short-urls';

it('API-02-T01 0件のとき、空の一覧を返す', function (): void {
    $this->getJson(LIST_SHORT_URLS)
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

it('API-02-T02 登録済みのURLを仕様の構造で返し、内部IDを含めない', function (): void {
    config(['shorturl.base_url' => 'http://localhost:8000']);
    ShortUrl::factory()->count(3)->create();

    $response = $this->getJson(LIST_SHORT_URLS)->assertOk();

    $response->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['code', 'short_url', 'original_url', 'clicks_count', 'created_at']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to', 'path', 'links'],
        ]);

    foreach ($response->json('data') as $item) {
        expect($item)->toHaveKeys(['code', 'short_url', 'original_url', 'clicks_count', 'created_at'])
            ->and($item)->toHaveCount(5)
            ->and($item['short_url'])->toBe('http://localhost:8000/'.$item['code']);
    }
});

it('API-02-T03 作成日時の新しい順に並べる', function (): void {
    $oldest = ShortUrl::factory()->create(['created_at' => now()->subDays(2)]);
    $newest = ShortUrl::factory()->create(['created_at' => now()]);
    $middle = ShortUrl::factory()->create(['created_at' => now()->subDay()]);

    $codes = $this->getJson(LIST_SHORT_URLS)->assertOk()->json('data.*.code');

    expect($codes)->toBe([$newest->code, $middle->code, $oldest->code]);
});

it('API-02-T04 作成日時が同じ場合は、後に登録したもの（IDの大きいもの）を先に並べる', function (): void {
    $this->freezeTime();
    $first = ShortUrl::factory()->create();
    $second = ShortUrl::factory()->create();

    $codes = $this->getJson(LIST_SHORT_URLS)->assertOk()->json('data.*.code');

    expect($codes)->toBe([$second->code, $first->code]);
});

it('API-02-T05 クリック数を返す', function (): void {
    $a = ShortUrl::factory()->create(['created_at' => now()]);
    $b = ShortUrl::factory()->create(['created_at' => now()->subDay()]);
    Click::factory()->count(3)->for($a)->create();

    $response = $this->getJson(LIST_SHORT_URLS)->assertOk();

    $response->assertJsonPath('data.0.code', $a->code)
        ->assertJsonPath('data.0.clicks_count', 3)
        ->assertJsonPath('data.1.code', $b->code)
        ->assertJsonPath('data.1.clicks_count', 0);
});

it('API-02-T06 1ページ20件で返す', function (): void {
    ShortUrl::factory()->count(21)->create();

    $this->getJson(LIST_SHORT_URLS)
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 21);
});

it('API-02-T07 2ページ目は残りの1件（最も古いもの）を返す', function (): void {
    $oldest = ShortUrl::factory()->create(['created_at' => now()->subDay()]);
    ShortUrl::factory()->count(20)->create(['created_at' => now()]);

    $this->getJson(LIST_SHORT_URLS.'?page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', $oldest->code)
        ->assertJsonPath('meta.current_page', 2);
});

it('API-02-T08 存在しないページは空の一覧を200で返す', function (): void {
    ShortUrl::factory()->create();

    $this->getJson(LIST_SHORT_URLS.'?page=99')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('API-02-T09 page が1以上の整数でなければ422を返す', function (string $page): void {
    $this->getJson(LIST_SHORT_URLS.'?page='.$page)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page']);
})->with(['abc', '0', '-1', '1.5']);

it('API-02-T10 件数が増えても実行するクエリ数は変わらない（N+1がない）', function (): void {
    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(LIST_SHORT_URLS)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    Click::factory()->for(ShortUrl::factory())->create();
    $withOne = $countQueries();

    Click::factory()->count(19)->create();
    $withTwenty = $countQueries();

    expect($withTwenty)->toBe($withOne);
});

it('API-02-T11 1分間に61回リクエストすると、61回目は429を返す', function (): void {
    for ($i = 1; $i <= 60; $i++) {
        $this->getJson(LIST_SHORT_URLS)->assertOk();
    }

    $this->getJson(LIST_SHORT_URLS)->assertTooManyRequests();
});

it('API-02-T12 他のオリジンからのリクエストを許可しない（CORS）', function (): void {
    $this->getJson(LIST_SHORT_URLS, ['Origin' => 'http://evil.example'])
        ->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Origin');

    // プリフライト（OPTIONS）でも許可しない
    $this->call('OPTIONS', LIST_SHORT_URLS, server: [
        'HTTP_ORIGIN' => 'http://evil.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ])->assertHeaderMissing('Access-Control-Allow-Origin');
});
