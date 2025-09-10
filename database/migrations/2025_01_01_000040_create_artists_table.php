<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('title', 120);
            $table->text('bio')->nullable();
            $table->string('portrait_path', 255)->nullable();
            $table->unsignedTinyInteger('display_order')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index('is_visible');
            $table->index('display_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
