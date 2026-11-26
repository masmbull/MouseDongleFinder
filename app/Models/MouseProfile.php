<?php

namespace App\Models;

use Database\Factories\MouseProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MouseProfile extends Model
{
    /** @use HasFactory<MouseProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'brand',
        'model',
        'vid',
        'pid',
        'receiver_family',
        'wireless_technology',
        'frequency',
        'pairing_utility',
        'official_support_url',
        'notes',
        'source',
    ];
}
