<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('short_url_id');
            $table->timestamp('clicked_at');

            // 複合インデックスを外部キーのインデックスとしても使う
            $table->index(['short_url_id', 'clicked_at']);
            $table->foreign('short_url_id')->references('id')->on('short_urls')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clicks');
    }
};
