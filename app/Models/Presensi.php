<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presensi extends Model
{
    use HasFactory;

    protected $table = 'presensi';

    protected $fillable = [
        'siswa_id',
<<<<<<< HEAD
        'guru_staf_id',     // tambahkan agar bisa diisi otomatis dari user login
=======
        'guru_staf_id',
>>>>>>> master
        'tahun_ajaran_id',
        'tanggal',
        'status',
        'keterangan',
    ];

<<<<<<< HEAD
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
=======
    protected $casts = [
        'tanggal' => 'date',
        'siswa_id' => 'string',
        'guru_staf_id' => 'string',
        'tahun_ajaran_id' => 'string',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id', 'id');
>>>>>>> master
    }

    public function guruStaf(): BelongsTo
    {
<<<<<<< HEAD
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id');
=======
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id', 'id');
>>>>>>> master
    }

    public function tahunAjaran(): BelongsTo
    {
<<<<<<< HEAD
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }
}
=======
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id', 'id');
    }
}
>>>>>>> master
