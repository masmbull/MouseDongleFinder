<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Identification extends Model
{
    use HasFactory;

    protected $fillable = [
        'dongle_id',
        'vid',
        'pid',
        'hardware_id',
        'probable_manufacturer',
        'probable_model',
        'device_category',
        'wireless_technology',
        'frequency',
        'receiver_family',
        'compatible_mice',
        'pairing_utility',
        'official_support_url',
        'evidence',
        'confidence',
        'research_sources',
        'notes',
        'is_seed',
    ];

    protected $casts = [
        'compatible_mice' => 'array',
        'evidence' => 'array',
        'research_sources' => 'array',
        'is_seed' => 'boolean',
    ];

    public function dongle(): BelongsTo
    {
        return $this->belongsTo(Dongle::class);
    }
}
