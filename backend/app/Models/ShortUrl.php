<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShortUrlFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int|null $clicks_count withCount('clicks') / loadCount('clicks') で設定される
 */
class ShortUrl extends Model
{
    /** @use HasFactory<ShortUrlFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'original_url',
    ];

    /**
     * @return HasMany<Click, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(Click::class);
    }
}
