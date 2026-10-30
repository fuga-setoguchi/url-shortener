<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ClickFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Click extends Model
{
    /** @use HasFactory<ClickFactory> */
    use HasFactory;

    // 追記のみで更新しないため created_at / updated_at を持たない
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'short_url_id',
        'clicked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ShortUrl, $this>
     */
    public function shortUrl(): BelongsTo
    {
        return $this->belongsTo(ShortUrl::class);
    }
}
