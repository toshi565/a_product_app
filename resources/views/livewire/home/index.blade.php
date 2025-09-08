<?php

use App\Models\Artist;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

            // ストレージ上に実体が無いレコードを除外
            $disk = Storage::disk('public');
            if ($images->isNotEmpty()) {
                $images = $images->filter(fn($img) => $disk->exists($img->path))->values();
            }

            // DBに画像が無い／または全て欠落している場合はストレージから自動フォールバック
            if ($images->isEmpty()) {
                $candidates = collect();
                foreach ([$product ? "products/{$product->id}" : null, 'products'] as $folder) {
                    if (!$folder) {
                        continue;
                    }
                    $files = collect($disk->files($folder))->filter(fn($f) => preg_match('/\.(jpe?g|png|webp|gif)$/i', $f))->sort()->values();
                    if ($files->isNotEmpty()) {
                        $candidates = $files->map(function ($path, $index) use ($product) {
                            return (object) [
                                'path' => $path,
                                'alt_text' => $product?->title,
                                'position' => $index + 1,
                            ];
                        });
                        break;
                    }
                }
                $images = $candidates->take(10);
            }
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
                <div
                    class="relative overflow-hidden rounded-2xl border bg-gradient-to-b from-gray-50 to-white p-6 shadow-sm md:p-8">
                    <div
                        class="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-rose-100 opacity-30 blur-3xl">
                    </div>
                    <div
                        class="pointer-events-none absolute -bottom-24 -right-24 h-72 w-72 rounded-full bg-indigo-100 opacity-30 blur-3xl">
                    </div>

                    <div class="relative z-10 grid grid-cols-1 gap-8 md:grid-cols-2">
                        <div class="relative" id="product-gallery">
                            @if ($images->isNotEmpty())
                                <div class="space-y-3">
                                    @php $main = $images->first(); @endphp
                                    <div class="relative">
                                        <img data-main-image class="w-full rounded border object-cover"
                                            src="{{ asset('storage/' . $main->path) }}"
                                            alt="{{ $main->alt_text ?? $product->title }}">
                                        @if (session('purchase_completed'))
                                            <div
                                                class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                                <span
                                                    class="rounded bg-black/60 px-6 py-2 text-2xl font-bold tracking-widest text-white">SOLD
                                                    OUT</span>
                                            </div>
                                        @endif
                                    </div>
                                    @if ($images->count() >= 1)
                                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                            @foreach ($images as $thumb)
                                                <img data-thumb data-src="{{ asset('storage/' . $thumb->path) }}"
                                                    data-alt="{{ $thumb->alt_text ?? $product->title }}"
                                                    class="h-20 w-full cursor-pointer rounded border object-cover transition hover:opacity-80 focus:outline-none {{ $loop->first ? 'ring-2 ring-black' : '' }}"
                                                    src="{{ asset('storage/' . $thumb->path) }}"
                                                    alt="{{ $thumb->alt_text ?? $product->title }}" role="button"
                                                    tabindex="0" {{ $loop->first ? 'aria-current=true' : '' }}>
                                            @endforeach
                                        </div>
                                        <script>
                                            (function() {
                                                var gallery = document.getElementById('product-gallery');
                                                if (!gallery) return;
                                                var mainImg = gallery.querySelector('[data-main-image]');
                                                if (!mainImg) return;
                                                var thumbs = gallery.querySelectorAll('[data-thumb]');

                                                function setMain(src, alt) {
                                                    if (src) mainImg.src = src;
                                                    if (alt) mainImg.alt = alt;
                                                }

                                                function activateThumb(active) {
                                                    thumbs.forEach(function(el) {
                                                        el.classList.remove('ring-2', 'ring-black');
                                                        el.removeAttribute('aria-current');
                                                    });
                                                    if (active) {
                                                        active.classList.add('ring-2', 'ring-black');
                                                        active.setAttribute('aria-current', 'true');
                                                    }
                                                }
                                                thumbs.forEach(function(t) {
                                                    var src = t.getAttribute('data-src') || t.getAttribute('src');
                                                    var alt = t.getAttribute('data-alt') || t.getAttribute('alt') || '';
                                                    t.addEventListener('click', function() {
                                                        setMain(src, alt);
                                                        activateThumb(t);
                                                    });
                                                    t.addEventListener('keydown', function(e) {
                                                        if (e.key === 'Enter' || e.key === ' ') {
                                                            e.preventDefault();
                                                            t.click();
                                                        }
                                                    });
                                                });
                                            })();
                                        </script>
                                    @endif
                                </div>
                            @else
                                <div class="relative aspect-video w-full rounded border bg-gray-100">
                                    @if (session('purchase_completed'))
                                        <div
                                            class="pointer-events-none absolute inset-0 flex items-center justify-center">
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
