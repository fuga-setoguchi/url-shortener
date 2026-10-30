<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Click;
use App\Models\ShortUrl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Click>
 */
class ClickFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'short_url_id' => ShortUrl::factory(),
            'clicked_at' => now(),
        ];
    }
}
