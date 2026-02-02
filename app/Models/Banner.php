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
        'aktif_sampai' => 'date', // hanya tanggal
    ];

    protected $appends = ['foto_url']; // otomatis ikut di JSON

    // Scope untuk banner aktif
    public function scopeAktif($query, $date = null)
    {
        $date = $date ?? now();
        return $query->where('aktif_sampai', '>=', $date);
    }

    // Accessor untuk foto_url
    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? asset('storage/'.$this->foto) : null;
    }
}
