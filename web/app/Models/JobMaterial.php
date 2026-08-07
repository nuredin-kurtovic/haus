<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id', 'name', 'purchase_price', 'qty', 'markup_pct', 'discount_pct', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'qty' => 'decimal:2',
            'markup_pct' => 'integer',
            'discount_pct' => 'integer',
            'line_total' => 'decimal:2',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
