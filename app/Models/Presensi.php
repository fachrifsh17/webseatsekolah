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

    // Sesuaikan fillable dengan kolom di tabel terbaru
    protected $fillable = [
        'tanggal',
        'kelas_wali_id', // Ini merujuk ke tabel kelas_wali_kelas
    ];

    protected $casts = [
        'tanggal' => 'date',
        'kelas_wali_id' => 'integer',
    ];

    /**
     * Relasi ke tabel detail (Siswa yang diabsen)
     */
    public function details(): HasMany
    {
        // Tetap menggunakan presensi_id sebagai foreign key di tabel presensi_detail
        return $this->hasMany(PresensiDetail::class, 'presensi_id');
    }

    /**
     * Relasi ke Kelas Wali Kelas
     * Dari sini kamu bisa tarik data Kelas, Guru, dan Semester sekaligus
     */
    public function kelasWali(): BelongsTo
    {
        return $this->belongsTo(KelasWaliKelas::class, 'kelas_wali_id');
    }

    /**
     * Shortcut: Jika ingin langsung ambil nama kelas dari Presensi
     * Contoh: $presensi->nama_kelas
     */
    public function getNamaKelasAttribute()
    {
        return $this->kelasWali->kelas->nama_kelas ?? '-';
    }
}