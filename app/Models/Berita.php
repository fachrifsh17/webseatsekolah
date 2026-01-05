<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Berita extends Model
{
    use HasFactory;

    protected $table = 'berita';

    public $timestamps = true;

    protected $fillable = [
        'judul',
        'isi_berita',
        'tanggal_publikasi',
        'foto',
    ];

    protected $casts = [
        'tanggal_publikasi' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
