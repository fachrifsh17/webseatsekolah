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
        'cadis',
        'logo',
        'logo_provinsi',
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

    protected $appends = ['logo_url', 'logo_provinsi_url'];

    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo) {
            return url('storage/' . $this->logo);
        }
        return null;
    }

    public function getLogoProvinsiUrlAttribute(): ?string
    {
        if ($this->logo_provinsi) {
            return url('storage/' . $this->logo_provinsi);
        }
        return null;
    }

    public function guruStaf()
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id');
    }

    public function getKepalaSekolahAttribute(): ?string
    {
        return $this->guruStaf?->nama;
    }
}