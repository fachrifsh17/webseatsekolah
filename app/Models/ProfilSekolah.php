<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfilSekolah extends Model
{
    use HasFactory;

    protected $table = 'profil_sekolah';

    protected $fillable = [
        'nama_sekolah',
        'sejarah',
        'visi',
        'misi',
        'npsn',
        'akreditasi',
        'sambutan_kepsek',
        'guru_staf_id',
    ];

    protected $casts = [
        'guru_staf_id' => 'string',
        'npsn'         => 'string',
        'akreditasi'   => 'string',
    ];
    public function guruStaf()
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id');
    }
    public function getKepalaSekolahAttribute(): ?string
    {
        return $this->guruStaf?->nama;
    }
}
