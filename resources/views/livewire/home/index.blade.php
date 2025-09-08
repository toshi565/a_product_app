<?php

use App\Models\Artist;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use function Livewire\Volt\with;

with(function (): array {
    $settingJson = DB::table('site_settings')->where('key', 'current_product_id')->value('value');

    $productId = null;
    if ($settingJson) {
        $decoded = json_decode($settingJson, true);
        $productId = $decoded['id'] ?? null;
    }

    $product = null;
    $images = collect();

    if ($productId) {
        $product = Product::query()
            ->with(['images' => fn($q) => $q->orderBy('position')->limit(10)])
            ->find($productId);

        if ($product) {
            $images = $product->images;
        }
    }

    $artists = Artist::query()->where('is_visible', true)->orderBy('display_order')->limit(3)->get();

    return [
        'product' => $product,
        'images' => $images,
        'artists' => $artists,
    ];
});
?>

<div class="min-h-dvh">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <header class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="/images/logo.png" alt="Fushiyama" class="h-8 w-8" />
                <h1 class="text-2xl font-semibold">Fushiyama</h1>
            </div>
        </header>

        <section class="mb-12">
            @if (session('purchase_completed'))
                <div class="mb-4 rounded-lg border bg-green-50 p-4 text-green-800">
                    お買い上げありがとうございました。
                </div>
            @endif
            <h2 class="mb-4 text-xl font-bold">Weekly Product</h2>

            @if ($product)
                <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
                    <div class="relative">
                        @if ($images->isNotEmpty())
                            <div class="space-y-3">
                                @foreach ($images as $image)
                                    <div class="relative">
                                        <img class="w-full rounded border object-cover"
                                            src="{{ asset('storage/' . $image->path) }}"
                                            alt="{{ $image->alt_text ?? $product->title }}">
                                        @if (session('purchase_completed'))
                                            <div
                                                class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                                <span
                                                    class="rounded bg-black/60 px-6 py-2 text-2xl font-bold tracking-widest text-white">SOLD
                                                    OUT</span>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="relative aspect-video w-full rounded border bg-gray-100">
                                @if (session('purchase_completed'))
                                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                        <span
                                            class="rounded bg-black/60 px-6 py-2 text-2xl font-bold tracking-widest text-white">SOLD
                                            OUT</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-col gap-4">
                        <h3 class="text-2xl font-semibold">{{ $product->title }}</h3>
                        <div class="text-gray-600 whitespace-pre-line">{{ $product->description }}</div>
                        @if (is_array($product->specs) && !empty($product->specs))
                            <ul class="list-inside list-disc text-sm text-gray-600">
                                @foreach ($product->specs as $spec)
                                    <li>{{ $spec }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="mt-2 text-xl font-bold">¥ {{ number_format($product->price_yen) }}</div>
                        <div>
                            <a href="{{ route('payment.edit') }}"
                                class="inline-flex items-center rounded px-4 py-2 text-white {{ session('purchase_completed') ? 'bg-gray-400 cursor-not-allowed' : 'bg-black' }}"
                                @disabled(session('purchase_completed')) wire:navigate>BUY</a>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded border p-6 text-gray-600">現在表示できる商品がありません。</div>
            @endif
        </section>

        <section class="mb-16">
            <h2 class="mb-4 text-xl font-bold">Artists</h2>
            @if ($artists->isNotEmpty())
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3">
                    @foreach ($artists as $artist)
                        <div class="rounded border p-4">
                            @if ($artist->portrait_path)
                                <img class="mb-3 h-40 w-full rounded object-cover"
                                    src="{{ asset('storage/' . $artist->portrait_path) }}" alt="{{ $artist->name }}">
                            @endif
                            <div class="text-sm uppercase tracking-wide text-gray-500">{{ $artist->title }}</div>
                            <div class="text-lg font-semibold">{{ $artist->name }}</div>
                            @if ($artist->bio)
                                <p class="mt-2 text-sm text-gray-600">{{ $artist->bio }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded border p-6 text-gray-600">公開中のアーティストはいません。</div>
            @endif
        </section>

        <footer class="py-8 text-center text-sm text-gray-500">
            © {{ date('Y') }} Fushiyama
        </footer>
    </div>

</div>
