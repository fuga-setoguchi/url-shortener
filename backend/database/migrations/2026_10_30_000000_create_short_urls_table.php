<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('short_urls', function (Blueprint $table) {
            $table->id();
            // 大小文字を区別するため utf8mb4_bin（MySQLの既定照合順序は区別しない）
            $table->string('code', 16)->collation('utf8mb4_bin')->unique();
            $table->string('original_url', 2048);
            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_urls');
    }
};
