<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    public const MINI = 'haus-mini';

    public const PLUS = 'haus-plus';

    public const PRO = 'haus-pro';

    protected $fillable = [
        'name', 'slug', 'price_year', 'visits_per_year', 'deadline_hours',
        'emergency_deadline_hours', 'emergency_included', 'labor_discount_pct',
        'material_discount_pct', 'inspections_per_year', 'warranty_months',
        'is_per_apartment', 'sort', 'active',
    ];

    protected function casts(): array
    {
        return [
            'price_year' => 'decimal:2',
            'visits_per_year' => 'integer',
            'deadline_hours' => 'integer',
            'emergency_deadline_hours' => 'integer',
            'emergency_included' => 'boolean',
            'labor_discount_pct' => 'integer',
            'material_discount_pct' => 'integer',
            'inspections_per_year' => 'integer',
            'warranty_months' => 'integer',
            'is_per_apartment' => 'boolean',
            'sort' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
