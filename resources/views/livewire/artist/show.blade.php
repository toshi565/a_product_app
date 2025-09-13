<?php

use App\Models\Artist;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use function Livewire\Volt\with;

with(function (): array {
    /** @var string|int|null $param */
    $param = request()->route('artist');

    // ルートモデルバインディング互換（id想定）
    $artist = null;
    if (is_numeric($param)) {
        $artist = Artist::query()->find((int) $param);
    } else {
        $artist = Artist::query()->where('id', $param)->first();
    }

    if (!$artist || !$artist->is_visible) {
        throw new ModelNotFoundException('Artist not found');
    }

    return [
        'artist' => $artist,
    ];
});
?>

<div class="min-h-dvh">
    <div class="mx-auto max-w-3xl px-4 py-8">
        <header class="mb-8">
            <a href="{{ route('home') }}" class="text-sm text-brand-navy/60 hover:underline" wire:navigate>← ホームへ戻る</a>
        </header>

        <article class="rounded-2xl border border-brand-navy/20 bg-white p-6 shadow-sm">
            <div class="flex flex-col items-start gap-6 md:flex-row">
                @if ($artist->portrait_url)
                    <img src="{{ $artist->portrait_url }}" alt="{{ $artist->name }}"
                        class="h-48 w-48 flex-none rounded object-cover ring-1 ring-brand-navy/10" />
                @endif
                <div class="flex-1">
                    <div class="text-sm uppercase tracking-wide text-brand-navy/60">{{ $artist->title }}</div>
                    <h1 class="mt-1 text-2xl font-semibold text-brand-navy">{{ $artist->name }}</h1>
                    @if ($artist->genre)
                        <div class="mt-1 text-sm text-brand-navy/70">ジャンル: {{ $artist->genre }}</div>
                    @endif
                    @if ($artist->bio)
                        <div class="prose prose-sm mt-4 max-w-none text-brand-navy/80 whitespace-pre-line">
                            {{ $artist->bio }}</div>
                    @endif
                </div>
            </div>
        </article>

        <footer class="mt-8 text-center text-sm text-brand-navy/60">
            © {{ date('Y') }} Fushiyama
        </footer>
    </div>
</div>
