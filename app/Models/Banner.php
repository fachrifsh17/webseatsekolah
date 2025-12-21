<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $table = 'banner_pengumuman';

    protected $fillable = [
        'judul',
        'url_link',
        'aktif_sampai',
        'foto',
    ];

    protected $casts = [
        'aktif_sampai' => 'date',
    ];
}