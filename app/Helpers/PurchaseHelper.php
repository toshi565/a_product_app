<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class PurchaseHelper
{
    /**
     * 購入完了状態を取得
     */
    public static function isPurchaseCompleted(): bool
    {
        $settingJson = DB::table('site_settings')
            ->where('key', 'purchase_completed')
            ->value('value');

        if (!$settingJson) {
            return false;
        }

        $decoded = json_decode($settingJson, true);
        return $decoded['completed'] ?? false;
    }

    /**
     * 購入完了状態を設定
     */
    public static function setPurchaseCompleted(bool $completed = true): void
    {
        DB::table('site_settings')
            ->where('key', 'purchase_completed')
            ->update([
                'value' => json_encode(['completed' => $completed]),
            ]);
    }

    /**
     * 購入完了状態をリセット（管理者用）
     */
    public static function resetPurchaseStatus(): void
    {
        self::setPurchaseCompleted(false);
    }
}
