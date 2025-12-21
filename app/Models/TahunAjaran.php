<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TahunAjaran extends Model
{
    use HasFactory;

    protected $table = 'tahun_ajaran';

    protected $fillable = [
        'nama',
        'semester',
        'aktif'
    ];

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'tahun_ajaran_id');
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'tahun_ajaran_id');
    }

    public function poinSiswa(): HasMany
    {
        return $this->hasMany(PoinSiswa::class, 'tahun_ajaran_id');
    }

    public function jamSekolah(): HasMany
    {
        return $this->hasMany(JamSekolah::class, 'tahun_ajaran_id');
    }
}