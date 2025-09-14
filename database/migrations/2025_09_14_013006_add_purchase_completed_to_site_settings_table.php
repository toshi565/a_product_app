<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 購入完了状態を管理するための設定を追加（初期状態はfalse）
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'purchase_completed'],
            ['value' => json_encode(['completed' => false])]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('site_settings')->where('key', 'purchase_completed')->delete();
    }
};
