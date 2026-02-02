<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'kelas_id',
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

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id');
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

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'siswa_id');
    }

    public function poinSiswa(): HasMany
    {
        return $this->hasMany(PoinSiswa::class, 'siswa_id');
    }
}