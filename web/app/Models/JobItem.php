<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id', 'price_item_id', 'name', 'qty', 'base_price', 'discount_pct', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'base_price' => 'decimal:2',
            'discount_pct' => 'integer',
            'line_total' => 'decimal:2',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(PriceItem::class);
    }
}
