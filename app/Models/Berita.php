<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Berita extends Model
{
    use HasFactory;

    protected $table = 'berita';

    protected $fillable = [
        'judul',
        'isi_berita',
        'tanggal_publikasi',
        'foto',
    ];

    protected $casts = [
        'tanggal_publikasi' => 'date',
    ];
}