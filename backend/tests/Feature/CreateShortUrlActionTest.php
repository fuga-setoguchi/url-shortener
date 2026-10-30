<?php

declare(strict_types=1);

use App\Actions\CreateShortUrl;
use App\Models\ShortUrl;
use App\Support\ShortCodeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;

it('API-01-T31 コードが重複したら再生成して保存する', function (): void {
    ShortUrl::factory()->create(['code' => 'Exist01']);
    $this->mock(ShortCodeGenerator::class)
        ->shouldReceive('generate')
        ->twice()
        ->andReturn('Exist01', 'NewCode');

    $shortUrl = app(CreateShortUrl::class)->handle('https://example.com');

    expect($shortUrl->code)->toBe('NewCode');
    $this->assertDatabaseCount('short_urls', 2);
});

it('API-01-T32 コードの重複が3回続いたら例外を送出し、保存しない', function (): void {
    ShortUrl::factory()->create(['code' => 'Exist01']);
    $this->mock(ShortCodeGenerator::class)
        ->shouldReceive('generate')
        ->times(CreateShortUrl::MAX_ATTEMPTS)
        ->andReturn('Exist01');

    expect(fn () => app(CreateShortUrl::class)->handle('https://example.com'))
        ->toThrow(UniqueConstraintViolationException::class);

    $this->assertDatabaseCount('short_urls', 1);
});

it('コードは大小文字を区別して一意になる（exist01 と Exist01 は別のコード）', function (): void {
    ShortUrl::factory()->create(['code' => 'Exist01']);
    $this->mock(ShortCodeGenerator::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn('exist01');

    expect(app(CreateShortUrl::class)->handle('https://example.com')->code)->toBe('exist01');
});
