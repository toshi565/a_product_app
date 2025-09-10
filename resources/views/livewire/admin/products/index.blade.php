<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use function Livewire\Volt\state;
use function Livewire\Volt\with;
use function Livewire\Volt\mount;

with(function (): array {
    $settingJson = DB::table('site_settings')->where('key', 'current_product_id')->value('value');
    $productId = null;
    if ($settingJson) {
        $decoded = json_decode($settingJson, true);
        $productId = $decoded['id'] ?? null;
    }

    $product = $productId ? Product::find($productId) : null;

    return [
        'product' => $product,
    ];
});

state([
    'title' => fn(?Product $product) => $product?->title ?? '',
    'description' => fn(?Product $product) => $product?->description ?? '',
    'price_yen' => fn(?Product $product) => $product?->price_yen ?? 0,
    'specs' => fn(?Product $product) => $product?->specs ?? [],
    'images' => [], // アップロード用
]);

$save = function (?Product $product) {
    if (!$product) {
        session()->flash('status', '現在の商品がありません');
        return;
    }
    $validated = validate([
        'title' => ['required', 'string', 'max:100'],
        'description' => ['nullable', 'string'],
        'price_yen' => ['required', 'integer', 'min:0'],
        'specs' => ['nullable', 'array', 'max:10'],
        'specs.*' => ['string', 'max:200'],
        'images.*' => ['nullable', 'image', 'max:5120'], // 5MB
    ]);

    DB::transaction(function () use ($validated, $product) {
        $product->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'price_yen' => $validated['price_yen'],
            'specs' => $validated['specs'] ?? [],
        ]);

        // 画像保存
        if (!empty($validated['images'])) {
            $disk = Storage::disk('public');
            $dir = "products/{$product->id}";
            $existingCount = $product->images()->count();
            foreach ($validated['images'] as $idx => $file) {
                $position = $existingCount + $idx + 1;
                if ($position > 10) {
                    break;
                } // 最大10枚
                $path = $file->store($dir, 'public');
                $product->images()->create([
                    'path' => $path,
                    'alt_text' => $product->title,
                    'position' => $position,
                ]);
            }
        }
    });

    session()->flash('status', '保存しました');
};

mount(function (?Product $product) {
    // 念のため現在商品IDを site_settings に再保存（存在しなければ作成）
    if ($product) {
        DB::table('site_settings')->updateOrInsert(['key' => 'current_product_id'], ['value' => json_encode(['id' => $product->id], JSON_UNESCAPED_UNICODE)]);
    }
});
?>

<div class="mx-auto max-w-4xl px-4 py-8">
    <h1 class="mb-6 text-2xl font-semibold">商品管理</h1>

    @if (session('status'))
        <div class="mb-4 rounded border bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
    @endif

    @if ($product)
        <form wire:submit="save" class="space-y-6">
            <div>
                <label class="mb-1 block text-sm font-medium">商品名</label>
                <input type="text" wire:model.live="title" class="w-full rounded border px-3 py-2" />
                @error('title')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">説明文</label>
                <textarea rows="6" wire:model.live="description" class="w-full rounded border px-3 py-2"></textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">価格 (税込・円)</label>
                <input type="number" min="0" step="1" wire:model.live="price_yen"
                    class="w-full rounded border px-3 py-2" />
                @error('price_yen')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">仕様（最大10行）</label>
                <div class="space-y-2">
                    @foreach (range(0, 9) as $i)
                        <input type="text" wire:model.live="specs.{{ $i }}"
                            placeholder="仕様 {{ $i + 1 }}" class="w-full rounded border px-3 py-2" />
                    @endforeach
                </div>
                @error('specs.*')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">画像追加（最大10枚まで加算）</label>
                <input type="file" wire:model="images" multiple accept="image/*" class="block" />
                @error('images.*')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">アップロードすると既存の末尾に追加されます（上限10枚）。</p>
            </div>

            <div class="pt-2">
                <button type="submit" class="rounded bg-black px-4 py-2 text-white">保存</button>
                <a href="{{ route('home') }}" class="ml-3 text-sm text-gray-600 underline" wire:navigate>トップに戻る</a>
            </div>
        </form>
    @else
        <div class="rounded border p-6 text-gray-600">現在の商品が見つかりません。</div>
    @endif
</div>
