<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RateLimits extends Model
{
    use HasFactory;

    protected $table = 'rate_limits';

    protected $fillable = [
        'key_name',
        'attempts',
        'last_attempt',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'last_attempt' => 'datetime',
    ];
}