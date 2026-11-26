<?php

namespace App\Models;

use Database\Factories\MatchingSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use HasFactory<MatchingSessionFactory>
 */
class MatchingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'dongles',
        'observations',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'dongles' => 'array',
        'observations' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
