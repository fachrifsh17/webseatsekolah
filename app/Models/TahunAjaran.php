<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Tambahkan ini

class TahunAjaran extends Model
{
    use HasFactory;

    protected $table = 'tahun_ajaran';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nama',
        'semester',
        'kurikulum_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'kurikulum_id' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastId = static::max('id');
                $num = $lastId ? (int) substr($lastId, 2) + 1 : 1;
                $model->id = 'TA' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    /**
     * PERUBAHAN: Relasi ke Kelas tidak lagi HasMany langsung, 
     * melainkan BelongsToMany melalui tabel pivot 'siswa_kelas'.
     * Ini akan menampilkan daftar kelas yang memiliki siswa aktif di TA ini.
     */
    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'siswa_kelas', 'tahun_ajaran_id', 'kelas_id')
                    ->withPivot('is_active')
                    ->distinct(); // Gunakan distinct agar nama kelas tidak duplikat
    }

    // Relasi lainnya tetap sama karena mereka masih punya tahun_ajaran_id
    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'tahun_ajaran_id');
    }

    public function presensiGuruMapel(): HasMany
    {
        return $this->hasMany(PresensiGuruMapel::class, 'tahun_ajaran_id');
    }

    public function poinSiswa(): HasMany
    {
        return $this->hasMany(PoinSiswa::class, 'tahun_ajaran_id');
    }

    public function jamSekolah(): HasMany
    {
        return $this->hasMany(JamSekolah::class, 'tahun_ajaran_id');
    }

    public function kalenderAkademik(): HasMany
    {
        return $this->hasMany(KalenderAkademik::class, 'tahun_ajaran_id', 'id');
    }
}