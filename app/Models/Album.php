<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Album extends Model
{
    use HasFactory;

    protected $table = 'album';

    protected $fillable = [
        'nama_album',
        'tanggal_kegiatan',
        'cover_path',
    ];

    protected $casts = [
        'tanggal_kegiatan' => 'date:Y-m-d',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'album_id');
    }
}
