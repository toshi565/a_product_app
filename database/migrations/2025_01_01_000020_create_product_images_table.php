<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('alt_text', 150)->nullable();
            $table->unsignedTinyInteger('position');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['product_id', 'position']);
            // DBでの厳密な CHECK は一部エンジンで非対応のためアプリ層で担保
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
