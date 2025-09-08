<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->string('key', 50)->primary();
            $table->json('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
