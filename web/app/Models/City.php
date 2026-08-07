<?php

namespace App\Models;

use App\Enums\CityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'lat', 'lng', 'status'];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'status' => CityStatus::class,
        ];
    }

    public function properties(): HasMany
    {
        return $this->hasMany(SubscriptionProperty::class);
    }

    public function scopeAktivni($query)
    {
        return $query->where('status', CityStatus::Aktivan);
    }
}
