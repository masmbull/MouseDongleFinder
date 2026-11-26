<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Mouse extends Model
{
    use HasFactory;

    /** @var array<int, string> */
    protected $with = ['referencePhotos'];

    protected $fillable = [
        'mouse_id',
        'brand',
        'model',
        'color',
        'physical_description',
        'connectivity_type',
        'photo_path',
        'notes',
        'is_seed',
    ];

    protected $casts = [
        'is_seed' => 'boolean',
    ];

    protected $appends = ['photo_url', 'device_match_status'];

    public function referencePhotos(): MorphMany
    {
        return $this->morphMany(DevicePhoto::class, 'photoable')->orderBy('path');
    }

    public function match(): HasOne
    {
        return $this->hasOne(DeviceMatch::class)->latestOfMany();
    }

    public function matches()
    {
        return $this->hasMany(DeviceMatch::class);
    }

    public function getPhotoUrlAttribute(): string
    {
        return $this->photo_path
            ? asset('storage/'.$this->photo_path)
            : asset('images/placeholder-mouse.svg');
    }

    public function getDeviceMatchStatusAttribute(): string
    {
        $match = $this->match;

        return $match?->status ?? 'unmatched';
    }
}
