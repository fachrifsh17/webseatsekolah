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
        'semester_id',
        'kelas_id',
        'kelas_wali_id',
        'guru_id', // Tambahkan guru_id (String VARCHAR 10)
        'tanggal',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'semester_id' => 'integer',
        'kelas_wali_id' => 'integer',
    ];

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function kelasWali(): BelongsTo
    {
        return $this->belongsTo(KelasWaliKelas::class, 'kelas_wali_id');
    }

    /**
     * Relasi ke Guru yang melakukan presensi
     */
    public function guru(): BelongsTo
    {
        // Foreign key 'guru_id' (string) ke primary key 'id' di GuruStaf
        return $this->belongsTo(GuruStaf::class, 'guru_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PresensiDetail::class, 'presensi_id');
    }

    public function getNamaKelasAttribute()
    {
        return $this->kelas->nama_kelas ?? ($this->kelasWali->kelas->nama_kelas ?? '-');
    }
}