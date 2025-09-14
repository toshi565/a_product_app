<?php

use App\Helpers\PurchaseHelper;
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

    // 購入完了状態をチェック
    $isPurchaseCompleted = PurchaseHelper::isPurchaseCompleted();

    return [
        'product' => $product,
        'images' => $images,
        'artists' => $artists,
        'isPurchaseCompleted' => $isPurchaseCompleted,
    ];
});
?>

<div class="min-h-dvh">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <header class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="/images/logo.png" alt="Fushiyama" class="h-[2.75rem] w-[2.75rem]" />
                <h1 class="text-[1.75rem] font-semibold tracking-tight text-brand-navy">Weekly Product </h1>
            </div>
        </header>

        <section class="mb-12">
            @if (session('purchase_completed'))
                <div class="mb-4 rounded-lg border bg-brand-gold-50 p-4 text-brand-navy">
                    お買い上げありがとうございました。
                </div>
            @endif
            <h2 class="mb-4 text-xl font-bold text-brand-navy">１つの商品ための販売サイト ~ 探し物はこれだった。 ~</h2>

            <p class="mb-4 text-sm text-brand-navy/70">週に１度１つの商品が更新されます。</p>
            <p class="mb-4 text-sm text-brand-navy/70">買い手と売り手２人だけの物販サイト。</p>
            <p class="mb-4 text-sm text-brand-navy/70">商品はたった１つ。</p>

            @if ($product)
                <div
                    class="relative overflow-hidden rounded-2xl border border-brand-gold/30 bg-gradient-to-b from-brand-navy-50 to-white p-6 shadow-sm md:p-8">
                    <div
                        class="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-brand-gold-100 opacity-30 blur-3xl">
                    </div>
                    <div
                        class="pointer-events-none absolute -bottom-24 -right-24 h-72 w-72 rounded-full bg-brand-gold-50 opacity-40 blur-3xl">
                    </div>

                    <div class="relative z-10 grid grid-cols-1 gap-8 md:grid-cols-12">
                        <div class="relative md:col-span-7" id="product-gallery">
                            @if ($images->isNotEmpty())
                                <div class="space-y-3">
                                    @php $main = $images->first(); @endphp
                                    <div class="relative">
                                        <img data-main-image
                                            class="h-96 md:h-[28rem] lg:h-[36rem] xl:h-[42rem] 2xl:h-[48rem] w-auto max-w-full mx-auto rounded border border-brand-navy/20 bg-white object-contain"
                                            src="{{ asset('storage/' . $main->path) }}"
                                            alt="{{ $main->alt_text ?? $product->title }}">
                                        @if ($isPurchaseCompleted)
                                            <div
                                                class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                                <span
                                                    class="rounded bg-brand-navy/70 px-6 py-2 text-2xl font-bold tracking-widest text-white">SOLD
                                                    OUT</span>
                                            </div>
                                        @endif
                                    </div>
                                    @if ($images->count() >= 1)
                                        <div class="relative">
                                            <button type="button" aria-label="前へ" data-thumb-prev
                                                class="absolute left-0 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow ring-1 ring-brand-navy/20 hover:bg-white focus:outline-none focus:ring-2 focus:ring-brand-gold">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    fill="currentColor" class="h-5 w-5 text-brand-navy">
                                                    <path fill-rule="evenodd"
                                                        d="M15.78 4.22a.75.75 0 010 1.06L9.06 12l6.72 6.72a.75.75 0 11-1.06 1.06l-7.25-7.25a.75.75 0 010-1.06l7.25-7.25a.75.75 0 011.06 0z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                            <div data-thumbs-track
                                                class="flex gap-2 overflow-x-auto scroll-smooth px-8">
                                                @foreach ($images as $thumb)
                                                    <img data-thumb data-src="{{ asset('storage/' . $thumb->path) }}"
                                                        data-alt="{{ $thumb->alt_text ?? $product->title }}"
                                                        class="h-24 w-28 flex-none cursor-pointer rounded border border-brand-navy/20 object-cover transition hover:opacity-80 focus:outline-none {{ $loop->first ? 'ring-2 ring-brand-gold' : '' }}"
                                                        src="{{ asset('storage/' . $thumb->path) }}"
                                                        alt="{{ $thumb->alt_text ?? $product->title }}" role="button"
                                                        tabindex="0" {{ $loop->first ? 'aria-current=true' : '' }}>
                                                @endforeach
                                            </div>
                                            <button type="button" aria-label="次へ" data-thumb-next
                                                class="absolute right-0 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow ring-1 ring-brand-navy/20 hover:bg-white focus:outline-none focus:ring-2 focus:ring-brand-gold">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                    fill="currentColor" class="h-5 w-5 text-brand-navy">
                                                    <path fill-rule="evenodd"
                                                        d="M8.22 19.78a.75.75 0 010-1.06L14.94 12 8.22 5.28a.75.75 0 111.06-1.06l7.25 7.25a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                        <script>
                                            (function() {
                                                var gallery = document.getElementById('product-gallery');
                                                if (!gallery) return;
                                                var mainImg = gallery.querySelector('[data-main-image]');
                                                if (!mainImg) return;
                                                var thumbs = gallery.querySelectorAll('[data-thumb]');
                                                var track = gallery.querySelector('[data-thumbs-track]');
                                                var prevBtn = gallery.querySelector('[data-thumb-prev]');
                                                var nextBtn = gallery.querySelector('[data-thumb-next]');

                                                function setMain(src, alt) {
                                                    if (src) mainImg.src = src;
                                                    if (alt) mainImg.alt = alt;
                                                }

                                                function activateThumb(active) {
                                                    thumbs.forEach(function(el) {
                                                        el.classList.remove('ring-2', 'ring-brand-gold');
                                                        el.removeAttribute('aria-current');
                                                    });
                                                    if (active) {
                                                        active.classList.add('ring-2', 'ring-brand-gold');
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

                                                // サムネイルスライダー（ゆっくりスライド）
                                                if (track) {
                                                    var stepPx = (function() {
                                                        var first = thumbs[0];
                                                        if (!first) return 120;
                                                        var rect = first.getBoundingClientRect();
                                                        return Math.max(100, Math.floor(rect.width + 8));
                                                    })();

                                                    function scrollByStep(multiplier) {
                                                        try {
                                                            track.scrollBy({
                                                                left: stepPx * multiplier,
                                                                behavior: 'smooth'
                                                            });
                                                        } catch (e) {
                                                            track.scrollLeft += stepPx * multiplier;
                                                        }
                                                    }

                                                    if (prevBtn) prevBtn.addEventListener('click', function() {
                                                        scrollByStep(-2);
                                                    });
                                                    if (nextBtn) nextBtn.addEventListener('click', function() {
                                                        scrollByStep(2);
                                                    });

                                                    var autoDir = 1; // 1: 右へ, -1: 左へ
                                                    var autoTimer = null;

                                                    function startAuto() {
                                                        if (autoTimer) return;
                                                        autoTimer = setInterval(function() {
                                                            var maxScroll = track.scrollWidth - track.clientWidth;
                                                            if (track.scrollLeft <= 0) autoDir = 1;
                                                            if (Math.abs(track.scrollLeft - maxScroll) < 2) autoDir = -1;
                                                            scrollByStep(autoDir);
                                                        }, 4000); // ゆっくり（4秒間隔）
                                                    }

                                                    function stopAuto() {
                                                        if (!autoTimer) return;
                                                        clearInterval(autoTimer);
                                                        autoTimer = null;
                                                    }

                                                    startAuto();
                                                    track.addEventListener('mouseenter', stopAuto);
                                                    track.addEventListener('mouseleave', startAuto);
                                                    track.addEventListener('focusin', stopAuto);
                                                    track.addEventListener('focusout', startAuto);
                                                }
                                            })();
                                        </script>
                                    @endif
                                </div>
                            @else
                                <div
                                    class="relative w-full h-96 md:h-[28rem] lg:h-[36rem] xl:h-[42rem] 2xl:h-[48rem] rounded border border-brand-navy/20 bg-brand-navy-50 flex items-center justify-center">
                                    @if ($isPurchaseCompleted)
                                        <div
                                            class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                            <span
                                                class="rounded bg-brand-navy/70 px-6 py-2 text-2xl font-bold tracking-widest text-white">SOLD
                                                OUT</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="flex flex-col gap-4 md:col-span-5">
                            <h3 class="text-2xl font-semibold text-brand-navy">{{ $product->title }}</h3>
                            <div class="text-brand-navy/70 whitespace-pre-line">{{ $product->description }}</div>
                            @if (is_array($product->specs) && !empty($product->specs))
                                <ul class="list-inside list-disc text-sm text-brand-navy/70">
                                    @foreach ($product->specs as $spec)
                                        <li>{{ $spec }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="mt-3 flex flex-col items-end gap-2">
                                <div class="text-2xl font-semibold text-brand-navy text-right">¥
                                    {{ number_format($product->price_yen) }}</div>
                                @if ($isPurchaseCompleted)
                                    <button disabled
                                        class="inline-flex w-auto justify-self-end items-center rounded bg-gray-400 px-4 py-2 text-white shadow-sm cursor-not-allowed opacity-60">
                                        SOLD OUT
                                    </button>
                                @else
                                    <a href="{{ route('payment.edit') }}"
                                        class="inline-flex w-auto justify-self-end items-center rounded bg-brand-navy bg-neutral-900 px-4 py-2 text-white shadow-sm transition hover:bg-brand-navy/90 hover:bg-black focus:outline-none focus:ring-2 focus:ring-brand-gold focus:ring-offset-2"
                                        wire:navigate>BUY</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded border border-brand-navy/20 p-6 text-brand-navy/70">現在表示できる商品がありません。</div>
            @endif
        </section>

        <section class="mb-16">
            <h2 class="mb-4 text-xl font-bold text-brand-navy">Artists</h2>
            @if ($artists->isNotEmpty())
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3">
                    @foreach ($artists as $artist)
                        <a href="{{ route('artists.show', ['artist' => $artist->id]) }}" wire:navigate
                            class="block rounded border border-brand-navy/20 p-4 transition hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-gold">
                            @if ($artist->portrait_url)
                                <div class="mb-3 w-full overflow-hidden rounded bg-brand-navy-50">
                                    <img class="w-full h-56 md:h-64 object-cover" src="{{ $artist->portrait_url }}"
                                        alt="{{ $artist->name }}">
                                </div>
                            @endif
                            <div class="text-sm uppercase tracking-wide text-brand-navy/60">{{ $artist->title }}</div>
                            <div class="text-lg font-semibold text-brand-navy">{{ $artist->name }}</div>
                            @if ($artist->bio)
                                <p class="mt-2 text-sm text-brand-navy/70">{{ $artist->bio }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            @else
                <div class="rounded border border-brand-navy/20 p-6 text-brand-navy/70">公開中のアーティストはいません。</div>
            @endif
        </section>

        <footer class="py-8 text-center text-sm text-brand-navy/60">
            © {{ date('Y') }} Fushiyama
        </footer>
    </div>

</div>
