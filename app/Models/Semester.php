<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Semester extends Model
{
    use HasFactory;

    protected $table = 'semesters';
    
    protected $fillable = [
        'tahun_ajaran_id',
        'nama',
        'tahun', 
        'is_active',
    ];

    // --- RELASI BARU ---
    
    /**
     * Relasi ke Presensi Guru Mapel (Denormalized)
     * Memungkinkan kita menarik data: Semester::find(1)->presensiGuruMapel
     */
    public function presensiGuruMapel(): HasMany
    {
        return $this->hasMany(PresensiGuruMapel::class, 'semester_id');
    }

    // --- RELASI YANG SUDAH ADA (TETAP DIPERTAHANKAN) ---

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function waliKelas(): BelongsToMany
    {
        return $this->belongsToMany(GuruStaf::class, 'kelas_wali_kelas', 'semester_id', 'guru_staf_id')
                    ->withPivot('kelas_id', 'is_active')
                    ->withTimestamps();
    }

    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(SiswaKelas::class, 'semester_id');
    }

    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'siswa_kelas', 'semester_id', 'kelas_id')
                    ->withPivot('is_active')
                    ->distinct();
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'semester_id');
    }

    public function poinSiswa(): HasMany
    {
        return $this->hasMany(PoinSiswa::class, 'semester_id');
    }

    public function jamSekolah(): HasMany
    {
        return $this->hasMany(JamSekolah::class, 'semester_id');
    }

    public function kalenderAkademik(): HasMany
    {
        return $this->hasMany(KalenderAkademik::class, 'semester_id', 'id');
    }

    public function guruMapel(): HasMany
    {
        return $this->hasMany(GuruMapel::class, 'semester_id');
    }
}