<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('テストは開発用DBではなくテスト用DB（testing）で実行される', function (): void {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::connection()->getDatabaseName())->toBe('testing')
        ->and(Schema::hasTable('migrations'))->toBeTrue();
});
