<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Click;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * クリックを記録する。転送の応答を遅らせないよう、キューで非同期に処理する（API-03）。
 */
class RecordClick implements ShouldQueue
{
    use Queueable;

    // DBの一時的な失敗に備えて再試行し、それでも失敗したら failed_jobs に記録される
    public int $tries = 3;

    /**
     * @param  CarbonInterface  $clickedAt  アクセス時刻（キューの遅延で時刻がずれないよう、投入時に渡す）
     */
    public function __construct(
        public readonly int $shortUrlId,
        public readonly CarbonInterface $clickedAt,
    ) {}

    public function handle(): void
    {
        Click::create([
            'short_url_id' => $this->shortUrlId,
            'clicked_at' => $this->clickedAt,
        ]);
    }
}
