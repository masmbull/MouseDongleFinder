<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'mouse_id',
        'dongle_id',
        'status',
        'notes',
        'matched_at',
        'is_seed',
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'is_seed' => 'boolean',
    ];

    public function mouse(): BelongsTo
    {
        return $this->belongsTo(Mouse::class);
    }

    public function dongle(): BelongsTo
    {
        return $this->belongsTo(Dongle::class);
    }
}
