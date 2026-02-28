<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Kelas extends Model
{
    use HasFactory;

    protected $table = 'kelas';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nama_kelas',
        'jurusan_id',
        'tingkatan_id', // Ditambahkan: untuk relasi ke tabel tingkatan
        'is_active',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastId = static::max('id');
                $num = $lastId ? (int) substr($lastId, 1) + 1 : 1;
                $model->id = 'K' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Relasi ke GuruStaf melalui tabel pivot kelas_wali_kelas
     */
    public function waliKelas(): BelongsToMany
    {
        // Mengubah dari belongsTo menjadi belongsToMany
        return $this->belongsToMany(GuruStaf::class, 'kelas_wali_kelas', 'kelas_id', 'guru_staf_id')
                    ->withPivot('tahun_ajaran_id', 'is_active')
                    ->withTimestamps();
    }

    /**
     * Relasi ke tabel Tingkatan (lookup table)
     */
    public function tingkatan(): BelongsTo
    {
        // Menambahkan relasi ini
        return $this->belongsTo(Tingkatan::class, 'tingkatan_id');
    }

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }

    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(SiswaKelas::class, 'kelas_id');
    }

    public function siswa(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'siswa_kelas', 'kelas_id', 'siswa_id')
                    ->withPivot('is_active', 'tahun_ajaran_id')
                    ->withTimestamps();
    }

    public function guruMapel(): HasMany
    {
        return $this->hasMany(GuruMapel::class, 'kelas_id');
    }
}