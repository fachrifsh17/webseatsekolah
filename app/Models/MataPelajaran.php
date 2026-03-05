<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataPelajaran extends Model
{
    use HasFactory;

    protected $table = 'mata_pelajaran';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nama_mapel',
        'jurusan_id',
        'tipe_mapel',
        'kategori_mapel',
        'is_active',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastId = static::where('id', 'like', 'M%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->value('id');

                $num = $lastId ? (int) substr($lastId, 1) + 1 : 1;
                $model->id = 'M' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    // --- RELASI BARU ---

    /**
     * Relasi langsung ke Presensi Guru Mapel (Optimasi Query)
     * Memungkinkan: MataPelajaran::find('M001')->presensiGuruMapel
     */
    public function presensiGuruMapel(): HasMany
    {
        return $this->hasMany(PresensiGuruMapel::class, 'mapel_id', 'id');
    }

    // --- RELASI YANG SUDAH ADA ---

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }

    public function guruMapel(): HasMany
    {
        return $this->hasMany(GuruMapel::class, 'mata_pelajaran_id');
    }
}