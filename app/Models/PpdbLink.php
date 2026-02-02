<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PpdbLink extends Model
{
    use HasFactory;

    protected $table = 'ppdb_link';

    protected $fillable = [
        'url_link',
        'status_ppdb',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];
}
