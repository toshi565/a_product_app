<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * アーティストモデル
 */
class Artist extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'title',
        'genre',
        'bio',
        'portrait_path',
        'display_order',
        'is_visible',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * 許可するジャンル一覧
     *
     * @return list<string>
     */
    public static function allowedGenres(): array
    {
        return [
            'クラフト作家',
            'ワインの輸入元',
            'コーヒーの焙煎家',
        ];
    }

    /**
     * ポートレート画像の公開URLを返す（存在チェックとフォールバックを含む）
     *
     * @return string|null
     */
    public function getPortraitUrlAttribute(): ?string
    {
        $disk = Storage::disk('public');

        // 明示的なパスがあり、実体がある場合
        $path = $this->portrait_path;
        if (is_string($path) && $path !== '' && $disk->exists($path)) {
            return asset('storage/' . $path);
        }

        // 規約: artists/{id}/portrait.jpg
        if ($this->id) {
            $defaultPath = "artists/{$this->id}/portrait.jpg";
            if ($disk->exists($defaultPath)) {
                return asset('storage/' . $defaultPath);
            }

            // 同フォルダ内の最初の画像ファイルにフォールバック
            $files = collect($disk->files("artists/{$this->id}"))
                ->filter(fn($f) => preg_match('/\.(jpe?g|png|webp|gif)$/i', $f))
                ->sort()
                ->values();
            if ($files->isNotEmpty()) {
                return asset('storage/' . $files->first());
            }
        }

        // artists 直下の任意画像（開発時の簡易配置想定）
        $rootFiles = collect($disk->files('artists'))
            ->filter(fn($f) => preg_match('/\.(jpe?g|png|webp|gif)$/i', $f))
            ->sort()
            ->values();
        if ($rootFiles->isNotEmpty()) {
            // display_order を 1始まりのインデックスとして使用して選択（なければ0番目）
            $index = is_int($this->display_order) ? max(0, (int) $this->display_order - 1) : 0;
            if ($index < $rootFiles->count()) {
                return asset('storage/' . $rootFiles->get($index));
            }
            return asset('storage/' . $rootFiles->first());
        }

        return null;
    }
}
