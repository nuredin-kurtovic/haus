<?php

namespace App\Models;

use App\Enums\HomeRecordType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_property_id', 'job_id', 'type', 'title', 'body', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => HomeRecordType::class,
            'recorded_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(SubscriptionProperty::class, 'subscription_property_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
