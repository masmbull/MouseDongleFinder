<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DevicePhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'photoable_type',
        'photoable_id',
        'photo_type',
        'caption',
        'path',
        'source_url',
        'source_name',
        'is_seed',
    ];

    protected $casts = [
        'is_seed' => 'boolean',
    ];

    /** @var array<int, string> */
    protected $appends = ['url'];

    public function photoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return asset($this->path);
    }
}
