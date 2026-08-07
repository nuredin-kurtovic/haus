<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'icon', 'sort'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceItem::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }
}
