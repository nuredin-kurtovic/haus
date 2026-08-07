<?php

namespace App\Models;

use App\Enums\JobPhotoType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class JobPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['job_id', 'type', 'path'];

    protected function casts(): array
    {
        return ['type' => JobPhotoType::class];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function url(): string
    {
        return Storage::url($this->path);
    }
}
