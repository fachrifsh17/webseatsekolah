<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuruMapel extends Model
{
    use HasFactory;

    protected $table = 'guru_mapel';

    protected $fillable = [
        'guru_staf_id',
        'mata_pelajaran_id',
        'kelas_id',
        'semester_id', // --- PERUBAHAN DI SINI ---
        'hari',
        'jam_mulai_id',
        'jam_selesai_id',
    ];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    // --- PERUBAHAN DI SINI ---
    // Nama fungsi dan model yang dirujuk diubah ke Semester
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function jamMulai(): BelongsTo
    {
        return $this->belongsTo(JamSekolah::class, 'jam_mulai_id');
    }

    public function jamSelesai(): BelongsTo
    {
        return $this->belongsTo(JamSekolah::class, 'jam_selesai_id');
    }
}