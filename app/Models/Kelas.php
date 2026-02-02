<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'wali_kelas_id',
        'tahun_ajaran_id',
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

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'wali_kelas_id');
    }

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'kelas_id');
    }

    public function guruMapel(): HasMany
    {
        return $this->hasMany(GuruMapel::class, 'kelas_id');
    }
}
