<?php

use App\Models\Artist;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt as LivewireVolt;

test('管理者はアーティストを削除できる', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    // テスト用のアーティストを作成
    $artist = Artist::factory()->create([
        'name' => 'テストアーティスト',
        'title' => 'テスト肩書',
        'bio' => 'テストプロフィール',
        'display_order' => 1,
        'is_visible' => true,
    ]);

    // アーティスト削除を実行
    LivewireVolt::test('admin.artists.index')
        ->call('deleteArtist', $artist->id);

    // アーティストが削除されていることを確認
    expect(\App\Models\Artist::find($artist->id))->toBeNull();
});

test('アーティスト削除時にポートレート画像ファイルも削除される', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    Storage::fake('public');

    $artist = Artist::factory()->create([
        'portrait_path' => 'artists/1/portrait.jpg',
    ]);

    // 画像ファイルを作成
    Storage::disk('public')->put($artist->portrait_path, 'fake image content');

    // アーティスト削除を実行
    LivewireVolt::test('admin.artists.index')
        ->call('deleteArtist', $artist->id);

    // 画像ファイルが削除されていることを確認
    Storage::disk('public')->assertMissing($artist->portrait_path);
});

test('編集中のアーティストを削除すると編集状態がクリアされる', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $artist = Artist::factory()->create();

    // アーティスト編集を開始
    $component = LivewireVolt::test('admin.artists.index')
        ->call('startEdit', $artist->id);

    // 編集状態を確認
    expect($component->editingId)->toBe($artist->id);

    // アーティスト削除を実行
    $component->call('deleteArtist', $artist->id);

    // 編集状態がクリアされていることを確認
    expect($component->editingId)->toBeNull();
    expect($component->name)->toBe('');
    expect($component->title)->toBe('');
    expect($component->bio)->toBe('');
    expect($component->display_order)->toBeNull();
    expect($component->is_visible)->toBeTrue();
});

test('存在しないアーティストの削除はエラーになる', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    LivewireVolt::test('admin.artists.index')
        ->call('deleteArtist', 999);

    // エラーが発生してもアーティストは削除されないことを確認
    expect(\App\Models\Artist::count())->toBe(0);
});

test('ポートレート画像がないアーティストも削除できる', function () {
    $user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($user);

    $artist = Artist::factory()->create([
        'portrait_path' => null,
    ]);

    LivewireVolt::test('admin.artists.index')
        ->call('deleteArtist', $artist->id);

    expect(\App\Models\Artist::find($artist->id))->toBeNull();
});
