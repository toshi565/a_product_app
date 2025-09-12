<?php

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use function Livewire\Volt\uses;
use function Livewire\Volt\state;
use function Livewire\Volt\with;
use function Livewire\Volt\mount;

uses([WithFileUploads::class]);

with(function (): array {
    $settingJson = DB::table('site_settings')->where('key', 'current_product_id')->value('value');
    $currentProductId = null;
    if ($settingJson) {
        $decoded = json_decode($settingJson, true);
        $currentProductId = $decoded['id'] ?? null;
    }

    $currentProduct = $currentProductId ? Product::find($currentProductId) : null;

    $products = Product::query()->orderByRaw("CASE WHEN status = 'published' THEN 0 ELSE 1 END")->orderByDesc('published_at')->orderByDesc('created_at')->limit(50)->get();

    return [
        'currentProductId' => $currentProductId,
        'product' => $currentProduct,
        'products' => $products,
    ];
});

state([
    'editingProductId' => null,
    'title' => fn(?Product $product) => $product?->title ?? '',
    'description' => fn(?Product $product) => $product?->description ?? '',
    'price_yen' => fn(?Product $product) => $product?->price_yen ?? 0,
    'specs' => fn(?Product $product) => $product?->specs ?? [],
    'images' => [], // 追加アップロード用
    'imagesForEditing' => [], // 既存画像の一覧
    'imageAlts' => [], // 画像ID => alt_text
]);

$save = function () {
    if (!$this->editingProductId) {
        session()->flash('status', '編集中の商品がありません');
        return;
    }
    $validated = $this->validate([
        'title' => ['required', 'string', 'max:100'],
        'description' => ['nullable', 'string'],
        'price_yen' => ['required', 'integer', 'min:0'],
        'specs' => ['nullable', 'array', 'max:10'],
        'specs.*' => ['nullable', 'string', 'max:200'],
        'images.*' => ['nullable', 'image', 'max:5120'], // 5MB
    ]);

    DB::transaction(function () use ($validated) {
        $product = Product::find($this->editingProductId);
        if (!$product) {
            throw new \RuntimeException('商品が見つかりません');
        }

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

        // alt_text の更新
        if (!empty($this->imageAlts) && is_array($this->imageAlts)) {
            foreach ($this->imageAlts as $imageId => $alt) {
                $img = ProductImage::where('product_id', $product->id)->where('id', (int) $imageId)->first();
                if ($img) {
                    $img->update(['alt_text' => (string) $alt]);
                }
            }
        }
    });

    session()->flash('status', '保存しました');
    $this->refreshImagesForEditing();
};

/**
 * 画像のみアップロード（即時反映）
 */
$uploadImages = function () {
    if (!$this->editingProductId) {
        session()->flash('status', '編集中の商品がありません');
        return;
    }

    $validated = $this->validate([
        'images' => ['required', 'array'],
        'images.*' => ['image', 'max:5120'], // 5MB
    ]);

    $added = 0;
    DB::transaction(function () use ($validated, &$added) {
        $product = Product::find($this->editingProductId);
        if (!$product) {
            throw new \RuntimeException('商品が見つかりません');
        }

        $disk = Storage::disk('public');
        $dir = "products/{$product->id}";
        $existingCount = $product->images()->count();

        foreach ($validated['images'] as $idx => $file) {
            $position = $existingCount + $added + 1;
            if ($position > 10) {
                break; // 上限10枚
            }
            $path = $file->store($dir, 'public');
            $product->images()->create([
                'path' => $path,
                'alt_text' => $product->title,
                'position' => $position,
            ]);
            $added++;
        }
    });

    $this->images = [];
    $this->refreshImagesForEditing();
    session()->flash('status', "画像を{$added}枚アップロードしました");
};

/**
 * 公開/下書き切替
 */
$togglePublish = function (int $productId) {
    $product = Product::find($productId);
    if (!$product) {
        session()->flash('status', '対象の商品が見つかりません');
        return;
    }

    DB::transaction(function () use ($product) {
        if ($product->status === 'published') {
            $product->update([
                'status' => 'draft',
                'published_at' => null,
            ]);
        } else {
            $product->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        }
    });

    session()->flash('status', '公開状態を更新しました');
};

/**
 * 現在の商品として設定（未公開の場合は公開にしてから設定）
 */
$setCurrent = function (int $productId) {
    $product = Product::find($productId);
    if (!$product) {
        session()->flash('status', '対象の商品が見つかりません');
        return;
    }

    DB::transaction(function () use ($product) {
        if ($product->status !== 'published') {
            $product->update([
                'status' => 'published',
                'published_at' => now(),
            ]);
        }
        DB::table('site_settings')->updateOrInsert(['key' => 'current_product_id'], ['value' => json_encode(['id' => $product->id], JSON_UNESCAPED_UNICODE)]);
    });

    session()->flash('status', '現在表示する商品を更新しました');
};

/**
 * 下書き商品を新規作成
 */
$createDraft = function () {
    $userId = Auth::id();
    $p = Product::create([
        'title' => 'New Product',
        'description' => '',
        'specs' => [],
        'price_yen' => 0,
        'status' => 'draft',
        'created_by' => $userId,
    ]);
    session()->flash('status', '下書き商品を作成しました');
    // 作成直後に編集対象へセット
    $this->editingProductId = $p->id;
    $this->loadProductIntoState($p);
};

mount(function (?Product $product) {
    if ($product) {
        $this->editingProductId = $product->id;
        $this->loadProductIntoState($product);
    }
});

/** 任意の商品を編集開始 */
$startEdit = function (int $productId) {
    $product = Product::find($productId);
    if (!$product) {
        session()->flash('status', '対象の商品が見つかりません');
        return;
    }
    $this->editingProductId = $product->id;
    $this->loadProductIntoState($product);
};

/** 画像を削除 */
$deleteImage = function (int $imageId) {
    if (!$this->editingProductId) {
        return;
    }
    $img = ProductImage::where('product_id', $this->editingProductId)->where('id', $imageId)->first();
    if (!$img) {
        return;
    }

    DB::transaction(function () use ($img) {
        $disk = Storage::disk('public');
        if ($img->path && $disk->exists($img->path)) {
            $disk->delete($img->path);
        }
        $productId = $img->product_id;
        $img->delete();

        // 位置を詰める
        $images = ProductImage::where('product_id', $productId)->orderBy('position')->get();
        foreach ($images as $index => $image) {
            $image->update(['position' => $index + 1]);
        }
    });

    $this->refreshImagesForEditing();
};

/** プロダクトをstateに読み込み */
$loadProductIntoState = function (Product $product) {
    $this->title = $product->title;
    $this->description = $product->description ?? '';
    $this->price_yen = (int) $product->price_yen;
    $this->specs = is_array($product->specs) ? $product->specs : [];
    $this->images = [];
    $this->refreshImagesForEditing();
};

/** 画像一覧をstateに再読み込み */
$refreshImagesForEditing = function () {
    if (!$this->editingProductId) {
        $this->imagesForEditing = [];
        $this->imageAlts = [];
        return;
    }
    $images = ProductImage::where('product_id', $this->editingProductId)
        ->orderBy('position')
        ->limit(10)
        ->get(['id', 'path', 'alt_text', 'position'])
        ->map(
            fn($i) => [
                'id' => $i->id,
                'path' => $i->path,
                'alt_text' => $i->alt_text,
                'position' => $i->position,
            ],
        )
        ->toArray();
    $this->imagesForEditing = $images;
    $this->imageAlts = collect($images)->mapWithKeys(fn($i) => [$i['id'] => (string) ($i['alt_text'] ?? '')])->toArray();
};
?>

<div class="mx-auto max-w-5xl px-4 py-8">
    <h1 class="mb-6 text-2xl font-semibold">商品管理</h1>

    @if (session('status'))
        <div class="mb-4 rounded border bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
    @endif

    <section class="mb-8 rounded-lg border bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold">商品一覧（最新50件）</h2>
            <div class="flex items-center gap-2">
                <button wire:click="createDraft" type="button"
                    class="rounded bg-neutral-900 px-3 py-2 text-white hover:bg-black">新規作成</button>
                <a href="{{ route('home') }}" class="rounded border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    wire:navigate>サイトを確認</a>
            </div>
        </div>

        @if (isset($products) && $products->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-600">
                            <th class="py-2 pr-3">商品名</th>
                            <th class="py-2 pr-3">価格</th>
                            <th class="py-2 pr-3">状態</th>
                            <th class="py-2 pr-3">公開日</th>
                            <th class="py-2">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $row)
                            <tr class="border-b last:border-0">
                                <td class="py-2 pr-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ $row->title }}</span>
                                        @if ($currentProductId === $row->id)
                                            <span
                                                class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700">現在表示中</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-2 pr-3">¥ {{ number_format($row->price_yen) }}</td>
                                <td class="py-2 pr-3">
                                    @if ($row->status === 'published')
                                        <span
                                            class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">公開</span>
                                    @elseif ($row->status === 'draft')
                                        <span
                                            class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">下書き</span>
                                    @else
                                        <span
                                            class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">アーカイブ</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 text-gray-600">{{ $row->published_at?->format('Y-m-d') ?? '-' }}
                                </td>
                                <td class="py-2">
                                    <div class="flex items-center gap-2">
                                        <button type="button" wire:click="startEdit({{ $row->id }})"
                                            class="rounded bg-neutral-900 px-2.5 py-1.5 text-xs text-white hover:bg-black">編集</button>
                                        <button type="button" wire:click="togglePublish({{ $row->id }})"
                                            class="rounded border px-2.5 py-1.5 text-xs hover:bg-gray-50">
                                            {{ $row->status === 'published' ? '下書きに戻す' : '公開する' }}
                                        </button>
                                        <button type="button" wire:click="setCurrent({{ $row->id }})"
                                            class="rounded bg-neutral-900 px-2.5 py-1.5 text-xs text-white hover:bg-black">現在商品に設定</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded border p-4 text-gray-600">商品がありません。</div>
        @endif
    </section>

    @if ($editingProductId)
        <form wire:submit.prevent="save" class="space-y-6 rounded-lg border bg-white p-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">商品を編集</h3>
                <div class="text-sm text-gray-500">ID: {{ $editingProductId }}</div>
            </div>
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
                <label class="mb-2 block text-sm font-medium">既存の画像（最大10枚）</label>
                @if (!empty($imagesForEditing))
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        @foreach ($imagesForEditing as $img)
                            <div class="rounded border p-2">
                                <img class="mb-2 aspect-video w-full rounded object-cover"
                                    src="{{ asset('storage/' . $img['path']) }}" alt="">
                                <label class="mb-1 block text-xs text-gray-600">Alt</label>
                                <input type="text" class="w-full rounded border px-2 py-1 text-sm"
                                    wire:model.live="imageAlts.{{ $img['id'] }}">
                                <div class="mt-2 text-right">
                                    <button type="button" wire:click="deleteImage({{ $img['id'] }})"
                                        class="rounded border px-2 py-1 text-xs text-red-600 hover:bg-red-50">削除</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded border p-4 text-gray-600">画像がありません。</div>
                @endif
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">画像追加（最大10枚まで加算）</label>
                <input id="images-input" type="file" wire:model="images" multiple accept="image/*" class="hidden" />
                <label for="images-input"
                    class="inline-flex cursor-pointer items-center rounded border px-3 py-1.5 text-sm hover:bg-gray-50">ファイルを選択</label>
                @error('images.*')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">アップロードすると既存の末尾に追加されます（上限10枚）。</p>
                <div class="mt-2 flex items-center gap-2">
                    <button type="button" wire:click="uploadImages" wire:loading.attr="disabled"
                        wire:target="uploadImages,images"
                        class="rounded bg-neutral-900 px-3 py-1.5 text-white hover:bg-black">アップロード</button>
                    <span class="text-sm text-gray-600" wire:loading
                        wire:target="uploadImages,images">アップロード中...</span>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="rounded bg-neutral-900 px-4 py-2 text-white hover:bg-black">保存</button>
                <a href="{{ route('home') }}" class="ml-3 text-sm text-gray-600 underline" wire:navigate>トップに戻る</a>
            </div>
        </form>
    @else
        <div class="rounded border p-6 text-gray-600">現在の商品が見つかりません。</div>
    @endif
</div>
