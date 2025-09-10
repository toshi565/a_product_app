<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Artist;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\PaymentProfile;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            // 管理者
            $admin = User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'is_admin' => true,
            ]);

            // 一般ユーザー
            $user = User::factory()->create([
                'name' => 'User',
                'email' => 'user@example.com',
                'password' => bcrypt('password'),
                'is_admin' => false,
            ]);

            // 決済プロフィール（説明用）
            PaymentProfile::create([
                'user_id' => $user->id,
                'card_brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => (int) now()->addYears(3)->format('Y'),
                'billing_name' => 'User',
                'country' => 'JP',
                'postal_code' => '100-0001',
                'region' => '東京都',
                'locality' => '千代田区',
                'line1' => '千代田1-1',
                'line2' => null,
            ]);

            // デモ商品
            $product = Product::create([
                'title' => 'Fushiyama Weekly Product',
                'description' => 'デモ商品の説明文です。',
                'specs' => [
                    '素材: Cotton',
                    'サイズ: Free',
                ],
                'price_yen' => 29800,
                'status' => 'published',
                'published_at' => now(),
                'created_by' => $admin->id,
            ]);

            // 画像（ダミーパス）
            foreach (range(1, 3) as $i) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => "products/{$product->id}/{$i}_demo.jpg",
                    'alt_text' => "デモ画像{$i}",
                    'position' => $i,
                ]);
            }

            // アーティスト最大3名
            foreach ([1, 2, 3] as $order) {
                Artist::create([
                    'name' => "Artist {$order}",
                    'title' => 'Creator',
                    'bio' => 'デモの略歴です。',
                    'portrait_path' => null,
                    'display_order' => $order,
                    'is_visible' => true,
                ]);
            }

            // サイト設定: 現在の商品ID
            DB::table('site_settings')->updateOrInsert(
                ['key' => 'current_product_id'],
                ['value' => json_encode(['id' => $product->id], JSON_UNESCAPED_UNICODE)]
            );
        });
    }
}
