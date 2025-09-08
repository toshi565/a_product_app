<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->unique();
            $table->enum('card_brand', ['visa', 'mastercard', 'amex', 'jcb', 'diners', 'discover', 'other']);
            $table->char('last4', 4);
            $table->unsignedTinyInteger('exp_month');
            $table->unsignedSmallInteger('exp_year');
            $table->string('billing_name', 100)->nullable();
            $table->char('country', 2)->default('JP');
            $table->string('postal_code', 20)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('locality', 100)->nullable();
            $table->string('line1', 150)->nullable();
            $table->string('line2', 150)->nullable();
            $table->string('external_customer_id', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_profiles');
    }
};
