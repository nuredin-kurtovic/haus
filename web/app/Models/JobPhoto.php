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

    /** Fotografije naloga uvijek idu na public disk. */
    public const DISK = 'public';

    protected $fillable = ['job_id', 'type', 'path'];

    protected function casts(): array
    {
        return ['type' => JobPhotoType::class];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Fotografije naloga stoje na public disku, pa i URL mora ici sa njega.
     * Podrazumijevani disk je local i dao bi putanju koja nista ne servira.
     */
    public function url(): string
    {
        return Storage::disk(self::DISK)->url($this->path);
    }
}
