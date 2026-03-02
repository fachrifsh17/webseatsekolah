<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presensi extends Model
{
    use HasFactory;

    protected $table = 'presensi';

    protected $fillable = [
        'tanggal',
        'kelas_id',
        'semester_id', // --- PERUBAHAN DI SINI ---
        'guru_staf_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'kelas_id' => 'string',
        'semester_id' => 'integer', // --- PERUBAHAN DI SINI ---
        'guru_staf_id' => 'string',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(PresensiDetail::class, 'presensi_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id');
    }

    // --- PERUBAHAN DI SINI ---
    // Nama fungsi dan model yang dirujuk diubah ke Semester
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'id');
    }

    public function guruStaf(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id', 'id');
    }
}