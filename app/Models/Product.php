<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 商品モデル
 */
class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'specs',
        'price_yen',
        'status',
        'published_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specs' => 'array',
            'price_yen' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 公開済み商品のスコープ
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
