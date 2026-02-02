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
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'nip',
        'nuptk',
        'nama',
        'jabatan_fungsional',
        'status_kepegawaian',
        'foto',
        'jurusan_id',
        'is_active',
    ];

    protected $casts = [
        'user_id'    => 'string',
        'jurusan_id' => 'string',
        'is_active'  => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastId = static::orderBy('id', 'desc')->first()?->id;
                $num = $lastId ? (int) substr($lastId, 1) + 1 : 1;
                $model->id = 'G' . str_pad($num, 3, '0', STR_PAD_LEFT);
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

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id', 'id');
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
        return $this->hasMany(Presensi::class, 'guru_staf_id', 'id');
    }

    public function poinSiswa(): HasMany
    {
        return $this->hasMany(PoinSiswa::class, 'guru_staf_id', 'id');
    }

    public function profilSekolah(): HasOne
    {
        return $this->hasOne(ProfilSekolah::class, 'guru_staf_id', 'id');
    }
}