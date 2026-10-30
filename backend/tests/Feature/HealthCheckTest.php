<?php

declare(strict_types=1);

it('ヘルスチェック（/up）が200を返す', function (): void {
    $this->get('/up')->assertOk();
});
