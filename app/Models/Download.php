<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Download extends Model
{
    use HasFactory;

    protected $fillable = [
        'dongle_id',
        'identification_id',
        'filename',
        'file_path',
        'source_url',
        'source_type',
        'manufacturer',
        'model',
        'software_type',
        'file_size',
        'sha256_hash',
        'download_status',
        'downloaded_at',
        'is_seed',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
        'is_seed' => 'boolean',
    ];

    public function dongle(): BelongsTo
    {
        return $this->belongsTo(Dongle::class);
    }

    public function identification(): BelongsTo
    {
        return $this->belongsTo(Identification::class);
    }
}
