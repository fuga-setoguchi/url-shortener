<?php

declare(strict_types=1);

use App\Support\ShortCodeGenerator;

it('API-01-T21 英数字7文字のコードを生成する', function (): void {
    expect((new ShortCodeGenerator)->generate())->toMatch('/^[A-Za-z0-9]{7}$/');
});

it('API-01-T22 1,000回生成しても、すべて形式どおりで重複しない', function (): void {
    $generator = new ShortCodeGenerator;

    $codes = array_map(fn (): string => $generator->generate(), range(1, 1000));

    expect($codes)->each->toMatch('/^[A-Za-z0-9]{7}$/')
        ->and(array_unique($codes))->toHaveCount(1000);
});
