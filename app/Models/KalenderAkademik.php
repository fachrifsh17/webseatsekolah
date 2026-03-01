<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KalenderAkademik extends Model
{
    use HasFactory;

    protected $table = 'kalender_akademik';

    protected $fillable = [
        'semester_id', // --- PERUBAHAN DI SINI ---
        'kegiatan',
        'tanggal_mulai',
        'tanggal_selesai',
        'kategori',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'datetime:Y-m-d',
        'tanggal_selesai' => 'datetime:Y-m-d',
    ];

    // --- PERUBAHAN DI SINI ---
    // Nama fungsi dan model yang dirujuk diubah ke Semester
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'id');
    }
}