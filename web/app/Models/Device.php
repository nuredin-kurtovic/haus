<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'fcm_token', 'platform'];

    protected function casts(): array
    {
        return ['platform' => DevicePlatform::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
