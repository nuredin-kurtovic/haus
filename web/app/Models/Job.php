<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\VisitSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Domenski nalog. Laravelova queue tabela je queue_jobs, pa nema kolizije.
 */
class Job extends Model
{
    use HasFactory;

    protected $table = 'jobs';

    protected $fillable = [
        'number', 'user_id', 'subscription_id', 'subscription_property_id',
        'price_category_id', 'technician_id', 'type', 'status', 'parent_job_id',
        'description', 'is_emergency', 'preferred_window', 'scheduled_window_start',
        'scheduled_window_end', 'deadline_at', 'deadline_missed_at', 'findings',
        'warranty_until', 'visit_source', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => JobType::class,
            'status' => JobStatus::class,
            'is_emergency' => 'boolean',
            'scheduled_window_start' => 'datetime',
            'scheduled_window_end' => 'datetime',
            'deadline_at' => 'datetime',
            'deadline_missed_at' => 'datetime',
            'warranty_until' => 'date',
            'visit_source' => VisitSource::class,
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(SubscriptionProperty::class, 'subscription_property_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PriceCategory::class, 'price_category_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'parent_job_id');
    }

    public function warrantyJobs(): HasMany
    {
        return $this->hasMany(Job::class, 'parent_job_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(JobItem::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(JobMaterial::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(JobPhoto::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
