<?php

namespace App\Models;

use App\Enums\SurchargeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Surcharge extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'label', 'type', 'value', 'sort', 'active'];

    protected function casts(): array
    {
        return [
            'type' => SurchargeType::class,
            'value' => 'decimal:2',
            'sort' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
