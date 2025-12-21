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

    public $incrementing = false;
    
    protected $casts = [
        'status_ppdb' => 'boolean',
    ];
}