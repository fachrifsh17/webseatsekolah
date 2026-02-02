<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Jabatan extends Model
{
    use HasFactory;

    protected $table = 'jabatans';

    protected $fillable = [
        'nama_jabatan',
        'slug',
        'keterangan',
    ];

    // Relasi ke tabel struktur_jabatan
    public function strukturJabatan()
    {
        return $this->hasMany(StrukturJabatan::class, 'jabatan_id');
    }

    protected static function boot()
    {
        parent::boot();
        
        // Otomatis membuat slug saat create/update
        static::creating(fn ($jabatan) => $jabatan->slug = Str::slug($jabatan->nama_jabatan));
        static::updating(fn ($jabatan) => $jabatan->slug = Str::slug($jabatan->nama_jabatan));
    }
}