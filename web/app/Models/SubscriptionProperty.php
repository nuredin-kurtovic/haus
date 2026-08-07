<?php

namespace App\Models;

use App\Enums\PropertyUse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionProperty extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id', 'city_id', 'street', 'use', 'contact_name',
        'contact_note', 'remaining_visits', 'remaining_inspections',
    ];

    protected function casts(): array
    {
        return [
            'use' => PropertyUse::class,
            'remaining_visits' => 'integer',
            'remaining_inspections' => 'integer',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function homeRecords(): HasMany
    {
        return $this->hasMany(HomeRecord::class);
    }
}
