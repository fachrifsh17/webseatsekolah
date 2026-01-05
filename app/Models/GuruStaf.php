<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GuruStaf extends Model
{
    use HasFactory;

    protected $table = 'guru_staf';

    protected $fillable = [
        'user_id',
        'nip',
        'nuptk',
        'nama',
        'jabatan_fungsional',
        'status_kepegawaian',
        'foto',
        'jurusan_id',
    ];

    protected $casts = [
        'user_id'    => 'integer',
        'jurusan_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }

    public function kelas(): HasOne
    {
        return $this->hasOne(Kelas::class, 'wali_kelas_id', 'id');
    }

    public function guruMapel(): HasMany
    {
        return $this->hasMany(GuruMapel::class, 'guru_staf_id', 'id');
    }

    public function strukturJabatan(): HasMany
    {
        return $this->hasMany(StrukturJabatan::class, 'guru_staf_id', 'id');
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'guru_id', 'id');
    }

    public function poinSiswa(): HasMany
    {
        return $this->hasMany(PoinSiswa::class, 'guru_id', 'id');
    }

    public function profilSekolah(): HasOne
    {
        return $this->hasOne(ProfilSekolah::class, 'guru_staf_id', 'id');
    }
}
