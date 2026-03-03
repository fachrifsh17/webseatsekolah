<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasWaliKelas extends Model
{
    use HasFactory;

    protected $table = 'kelas_wali_kelas';

    protected $fillable = [
        'kelas_id',
        'guru_staf_id',
        'semester_id',
        'is_active',
    ];

    /**
     * Relasi ke model Kelas
     * Karena di database kelas_id adalah varchar(10), pastikan relasi merujuk ke 'id'
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id');
    }

    /**
     * Relasi ke model GuruStaf
     * guru_staf_id juga varchar(10) di database
     */
    public function guruStaf(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id', 'id');
    }

    /**
     * Relasi ke model Semester
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'id');
    }

    /**
     * Relasi ke Presensi (Tambahan)
     * Agar kamu bisa melihat riwayat presensi yang dilakukan oleh Wali Kelas ini
     */
    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'kelas_wali_id');
    }
}