<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Dongle extends Model
{
    use HasFactory;

    /** @var array<int, string> */
    protected $with = ['referencePhotos'];

    protected $fillable = [
        'dongle_id',
        'brand',
        'model',
        'color',
        'physical_description',
        'receiver_type',
        'photo_path',
        'notes',
        'vid',
        'pid',
        'hardware_id',
        'serial_number',
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

    public function identification(): HasOne
    {
        return $this->hasOne(Identification::class)->latestOfMany();
    }

    public function downloads()
    {
        return $this->hasMany(Download::class);
    }

    public function getPhotoUrlAttribute(): string
    {
        return $this->photo_path
            ? asset('storage/'.$this->photo_path)
            : asset('images/placeholder-dongle.svg');
    }

    public function getDeviceMatchStatusAttribute(): string
    {
        $match = $this->match;

        return $match?->status ?? 'unmatched';
    }
}
