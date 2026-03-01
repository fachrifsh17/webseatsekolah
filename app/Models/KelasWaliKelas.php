<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KelasWaliKelas extends Model
{
    // Tentukan nama tabel jika tidak mengikuti konvensi Laravel
    protected $table = 'kelas_wali_kelas';

    // Tentukan field yang boleh diisi (mass assignable)
    protected $fillable = [
        'kelas_id',
        'guru_staf_id',
        'semester_id', // PERUBAHAN: Ganti tahun_ajaran_id jadi semester_id
        'is_active',
    ];

    /**
     * Relasi ke model Kelas
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * Relasi ke model GuruStaf
     */
    public function guruStaf(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class);
    }

    /**
     * Relasi ke model Semester
     */
    public function semester(): BelongsTo // PERUBAHAN: Ganti nama fungsi relasi
    {
        return $this->belongsTo(Semester::class); // PERUBAHAN: Ganti Model relasi
    }
}