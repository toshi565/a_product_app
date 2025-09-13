<?php

use App\Models\Artist;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;
use function Livewire\Volt\uses;
use function Livewire\Volt\with;

uses([WithFileUploads::class]);

with(function (): array {
    $artists = Artist::query()->orderBy('display_order')->get();
    return [
        'artists' => $artists,
    ];
});

state([
    'editingId' => null,
    'name' => '',
    'title' => '',
    'genre' => '',
    'bio' => '',
    'display_order' => null,
    'is_visible' => true,
    'portrait' => null,
]);

$startEdit = function (int $id) {
    $a = Artist::find($id);
    if (!$a) {
        session()->flash('status', '対象のアーティストが見つかりません');
        return;
    }
    $this->editingId = $a->id;
    $this->name = (string) $a->name;
    $this->title = (string) $a->title;
    $this->genre = (string) ($a->genre ?? '');
    $this->bio = (string) ($a->bio ?? '');
    $this->display_order = $a->display_order;
    $this->is_visible = (bool) $a->is_visible;
    $this->portrait = null;
};

$create = function () {
    $this->editingId = null;
    $this->name = '';
    $this->title = '';
    $this->genre = '';
    $this->bio = '';
    $this->display_order = null;
    $this->is_visible = true;
    $this->portrait = null;
};

$save = function () {
    $validated = $this->validate([
        'name' => ['required', 'string', 'max:80'],
        'title' => ['required', 'string', 'max:120'],
        'genre' => ['nullable', 'string', 'max:50', Rule::in(Artist::allowedGenres())],
        'bio' => ['nullable', 'string'],
        'display_order' => ['nullable', 'integer', 'between:1,3'],
        'is_visible' => ['boolean'],
        'portrait' => ['nullable', 'image', 'max:5120'],
    ]);

    $attrs = [
        'name' => $validated['name'],
        'title' => $validated['title'],
        'genre' => $validated['genre'] ?? null,
        'bio' => $validated['bio'] ?? null,
        'display_order' => $validated['display_order'] ?? null,
        'is_visible' => (bool) ($validated['is_visible'] ?? false),
    ];

    if ($this->editingId) {
        $artist = Artist::find($this->editingId);
        if (!$artist) {
            session()->flash('status', '保存対象が見つかりません');
            return;
        }
        $artist->update($attrs);
    } else {
        $artist = Artist::create($attrs);
    }

    if (!empty($validated['portrait'])) {
        $disk = Storage::disk('public');
        $dir = "artists/{$artist->id}";
        $disk->makeDirectory($dir);
        $path = $validated['portrait']->store($dir, 'public');
        $artist->update(['portrait_path' => $path]);
    }

    session()->flash('status', '保存しました');
    $this->portrait = null;
};

$toggleVisible = function (int $id) {
    $a = Artist::find($id);
    if (!$a) {
        return;
    }
    $a->update(['is_visible' => !$a->is_visible]);
};

/**
 * 写真のみアップロード（即時反映）
 */
$uploadPortrait = function () {
    if (!$this->editingId) {
        session()->flash('status', '編集中のアーティストがありません');
        return;
    }
    $validated = $this->validate([
        'portrait' => ['required', 'image', 'max:5120'],
    ]);

    $artist = Artist::find($this->editingId);
    if (!$artist) {
        session()->flash('status', '対象のアーティストが見つかりません');
        return;
    }

    $dir = "artists/{$artist->id}";
    $path = $validated['portrait']->store($dir, 'public');
    $artist->update(['portrait_path' => $path]);

    // 入力状態をクリアし、メッセージ表示
    $this->portrait = null;
    session()->flash('status', '写真を更新しました');
};

?>

<div class="mx-auto max-w-5xl px-4 py-8">
    <h1 class="mb-6 text-2xl font-semibold">アーティスト管理</h1>

    @if (session('status'))
        <div class="mb-4 rounded border bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
    @endif

    <section class="mb-8 rounded-lg border bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold">一覧</h2>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="create"
                    class="rounded bg-neutral-900 px-3 py-2 text-white hover:bg-black">新規作成</button>
                <a href="{{ route('home') }}" wire:navigate
                    class="rounded border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">サイトを確認</a>
            </div>
        </div>

        @if (isset($artists) && $artists->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b text-gray-600">
                            <th class="py-2 pr-3">表示順</th>
                            <th class="py-2 pr-3">氏名</th>
                            <th class="py-2 pr-3">肩書</th>
                            <th class="py-2 pr-3">ジャンル</th>
                            <th class="py-2 pr-3">公開</th>
                            <th class="py-2">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($artists as $row)
                            <tr class="border-b last:border-0">
                                <td class="py-2 pr-3">{{ $row->display_order ?? '-' }}</td>
                                <td class="py-2 pr-3">{{ $row->name }}</td>
                                <td class="py-2 pr-3">{{ $row->title }}</td>
                                <td class="py-2 pr-3">{{ $row->genre ?? '-' }}</td>
                                <td class="py-2 pr-3">
                                    @if ($row->is_visible)
                                        <span
                                            class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">公開</span>
                                    @else
                                        <span
                                            class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">非公開</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    <div class="flex items-center gap-2">
                                        <button type="button" wire:click="startEdit({{ $row->id }})"
                                            class="rounded bg-neutral-900 px-2.5 py-1.5 text-xs text-white hover:bg-black">編集</button>
                                        <button type="button" wire:click="toggleVisible({{ $row->id }})"
                                            class="rounded border px-2.5 py-1.5 text-xs hover:bg-gray-50">公開切替</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded border p-4 text-gray-600">アーティストがいません。</div>
        @endif
    </section>

    <form wire:submit.prevent="save" class="space-y-6 rounded-lg border bg-white p-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">{{ $editingId ? 'アーティストを編集' : 'アーティストを作成' }}</h3>
            @if ($editingId)
                <div class="text-sm text-gray-500">ID: {{ $editingId }}</div>
            @endif
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">氏名</label>
            <input type="text" wire:model.live="name" class="w-full rounded border px-3 py-2" />
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">肩書（英見出し）</label>
            <input type="text" wire:model.live="title" class="w-full rounded border px-3 py-2" />
            @error('title')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">ジャンル</label>
            <select wire:model.live="genre" class="w-full rounded border px-3 py-2">
                <option value="">選択してください</option>
                @foreach (\App\Models\Artist::allowedGenres() as $g)
                    <option value="{{ $g }}">{{ $g }}</option>
                @endforeach
            </select>
            @error('genre')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">プロフィール</label>
            <textarea rows="5" wire:model.live="bio" class="w-full rounded border px-3 py-2"></textarea>
            @error('bio')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium">表示順（1..3）</label>
                <input type="number" min="1" max="3" wire:model.live="display_order"
                    class="w-full rounded border px-3 py-2" />
                @error('display_order')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-center gap-2">
                <input id="visible" type="checkbox" wire:model.live="is_visible" class="h-4 w-4">
                <label for="visible" class="text-sm">公開</label>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">ポートレート</label>
            <input id="portrait-input" type="file" wire:model="portrait" accept="image/*" class="hidden" />
            <label for="portrait-input"
                class="inline-flex cursor-pointer items-center rounded border px-3 py-1.5 text-sm hover:bg-gray-50">ファイルを選択</label>
            <div class="mt-2 flex items-center gap-2">
                <button type="button" wire:click="uploadPortrait" wire:loading.attr="disabled"
                    wire:target="uploadPortrait,portrait"
                    class="rounded bg-neutral-900 px-3 py-1.5 text-white hover:bg-black">アップロード</button>
                <span class="text-sm text-gray-600" wire:loading wire:target="uploadPortrait,portrait">アップロード中...</span>
            </div>
            @error('portrait')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <div class="mt-2 text-sm text-gray-600" wire:loading wire:target="portrait">アップロード準備中...</div>
            @if ($portrait)
                <img src="{{ $portrait->temporaryUrl() }}" alt="" class="mt-2 h-32 w-32 rounded object-cover">
            @elseif ($editingId)
                @php $fresh = \App\Models\Artist::find($editingId); @endphp
                @if ($fresh && $fresh->portrait_url)
                    <img src="{{ $fresh->portrait_url }}" alt=""
                        class="mt-2 h-32 w-32 rounded object-cover">
                @endif
            @endif
        </div>

        <div class="pt-2">
            <button type="submit" class="rounded bg-neutral-900 px-4 py-2 text-white hover:bg-black">保存</button>
            <a href="{{ route('home') }}" class="ml-3 text-sm text-gray-600 underline" wire:navigate>トップに戻る</a>
        </div>
    </form>
</div>
