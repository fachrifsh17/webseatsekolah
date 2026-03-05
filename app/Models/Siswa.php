<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Siswa extends Model
{
    use HasFactory;

    protected $table = 'siswa';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'nis',
        'nisn',
        'nama_lengkap',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'foto',
        'no_telp_siswa',
        'alamat',
        'is_active',
    ];

    protected $casts = [
        'user_id'       => 'string',
        'is_active'     => 'boolean',
        'tanggal_lahir' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastId = static::max('id');
                $num = $lastId ? (int) substr($lastId, 1) + 1 : 1;
                $model->id = 'S' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(SiswaKelas::class, 'siswa_id', 'id');
    }

    public function kelasAktif(): HasOne
    {
        return $this->hasOne(SiswaKelas::class, 'siswa_id', 'id')->where('is_active', true);
    }

    public function orangtua(): BelongsToMany
    {
        return $this->belongsToMany(
            Orangtua::class,
            'orangtua_siswa',
            'siswa_id',
            'orangtua_id'
        )->withPivot('hubungan')
         ->withTimestamps();
    }

    /**
     * Relasi untuk Presensi Harian (Wali Kelas)
     */
    public function presensiDetail(): HasMany
    {
        return $this->hasMany(PresensiDetail::class, 'siswa_id');
    }

    /**
     * Relasi untuk Presensi Mata Pelajaran (Guru Mapel)
     * Ditambahkan untuk sinkronisasi dengan Controller Guru Mapel
     */
    public function presensiSiswaDetail(): HasMany
    {
        return $this->hasMany(PresensiSiswaDetail::class, 'siswa_id');
    }

    public function poinSiswa(): HasMany
    {
        return $this->hasMany(PoinSiswa::class, 'siswa_id');
    }
}