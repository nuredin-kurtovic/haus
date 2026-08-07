<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_category_id', 'name', 'base_price', 'draft_base_price',
        'unit', 'sort', 'active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'draft_base_price' => 'decimal:2',
            'sort' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PriceCategory::class, 'price_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /** Red ima neobjavljenu izmjenu. */
    public function hasUnpublishedChange(): bool
    {
        return $this->draft_base_price !== null
            && (float) $this->draft_base_price !== (float) $this->base_price;
    }
}
