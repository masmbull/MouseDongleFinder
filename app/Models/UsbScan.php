<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsbScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_id',
        'devices',
        'device_count',
        'scanned_at',
        'scanned_by',
        'is_seed',
    ];

    protected $casts = [
        'devices' => 'array',
        'scanned_at' => 'datetime',
        'is_seed' => 'boolean',
    ];
}
