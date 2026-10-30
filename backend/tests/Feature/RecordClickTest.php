<?php

declare(strict_types=1);

use App\Jobs\RecordClick;
use App\Models\Click;
use App\Models\ShortUrl;
use Illuminate\Support\Carbon;

it('API-03-T11 渡した短縮URLと日時でクリックを1件記録する', function (): void {
    $shortUrl = ShortUrl::factory()->create();
    $clickedAt = Carbon::parse('2026-10-29 07:30:00');

    (new RecordClick($shortUrl->id, $clickedAt))->handle();

    expect(Click::count())->toBe(1);
    $click = Click::sole();
    expect($click->short_url_id)->toBe($shortUrl->id)
        ->and($click->clicked_at->equalTo($clickedAt))->toBeTrue();
});

it('API-03-T12 同期キューで2回アクセスすると、クリックが2件記録され一覧のクリック数が2になる', function (): void {
    config(['queue.default' => 'sync']);
    ShortUrl::factory()->create(['code' => 'aB3xK9q']);

    $this->get('/aB3xK9q')->assertStatus(302);
    $this->get('/aB3xK9q')->assertStatus(302);

    expect(Click::count())->toBe(2);
    $this->getJson('/api/v1/short-urls')
        ->assertOk()
        ->assertJsonPath('data.0.clicks_count', 2);
});

it('ジョブは3回まで再試行する', function (): void {
    expect((new RecordClick(1, now()))->tries)->toBe(3);
});
