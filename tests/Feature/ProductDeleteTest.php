<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt as LivewireVolt;

test('管理者は商品を削除できる', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    // テスト用の商品を作成
    $product = Product::factory()->create([
        'title' => 'テスト商品',
        'description' => 'テスト説明',
        'price_yen' => 1000,
        'status' => 'draft',
    ]);

    // テスト用の画像を作成
    Storage::fake('public');
    $image = ProductImage::factory()->create([
        'product_id' => $product->id,
        'path' => 'products/1/test.jpg',
        'position' => 1,
    ]);

    // 商品削除を実行
    LivewireVolt::test('admin.products.index')
        ->call('deleteProduct', $product->id);

    // 商品が削除されていることを確認
    expect(\App\Models\Product::find($product->id))->toBeNull();
    expect(\App\Models\ProductImage::find($image->id))->toBeNull();
});

test('商品削除時に画像ファイルも削除される', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    Storage::fake('public');

    $product = Product::factory()->create();
    $image = ProductImage::factory()->create([
        'product_id' => $product->id,
        'path' => 'products/1/test.jpg',
    ]);

    // 画像ファイルを作成
    Storage::disk('public')->put($image->path, 'fake image content');

    // 商品削除を実行
    LivewireVolt::test('admin.products.index')
        ->call('deleteProduct', $product->id);

    // 画像ファイルが削除されていることを確認
    Storage::disk('public')->assertMissing($image->path);
});

test('現在表示中の商品を削除すると設定も解除される', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $product = Product::factory()->create(['status' => 'published']);

    // 現在の商品として設定
    \DB::table('site_settings')->insert([
        'key' => 'current_product_id',
        'value' => json_encode(['id' => $product->id]),
    ]);

    // 商品削除を実行
    LivewireVolt::test('admin.products.index')
        ->call('deleteProduct', $product->id);

    // 設定が削除されていることを確認
    expect(\DB::table('site_settings')->where('key', 'current_product_id')->exists())->toBeFalse();
});

test('編集中の商品を削除すると編集状態がクリアされる', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $product = Product::factory()->create();

    // 商品編集を開始
    $component = LivewireVolt::test('admin.products.index')
        ->call('startEdit', $product->id);

    // 編集状態を確認
    expect($component->editingProductId)->toBe($product->id);

    // 商品削除を実行
    $component->call('deleteProduct', $product->id);

    // 編集状態がクリアされていることを確認
    expect($component->editingProductId)->toBeNull();
    expect($component->title)->toBe('');
    expect($component->description)->toBe('');
    expect($component->price_yen)->toBe(0);
});

test('存在しない商品の削除はエラーになる', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    LivewireVolt::test('admin.products.index')
        ->call('deleteProduct', 999);

    // エラーが発生しても商品は削除されないことを確認
    expect(\App\Models\Product::count())->toBe(0);
});
