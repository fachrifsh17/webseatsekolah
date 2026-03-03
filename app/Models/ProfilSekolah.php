<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage; // Tambahkan ini untuk handle URL logo

class ProfilSekolah extends Model
{
    use HasFactory;

    protected $table = 'profil_sekolah';

    protected $fillable = [
        'nama_sekolah',
        'cadis',           // Tambahkan ini
        'logo',            // Tambahkan ini
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

    // --- Accessor untuk URL Logo (Agar dapet link lengkap di Resource) ---
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo) {
            return url('storage/' . $this->logo);
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