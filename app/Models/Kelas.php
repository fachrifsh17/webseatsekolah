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
        'wali_kelas_id',
        // 'tahun_ajaran_id', // <-- DIHAPUS karena kolom sudah tidak ada di tabel
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

    /**
     * Relasi tahunAjaran DIHAPUS karena tabel kelas 
     * sekarang bersifat statis (tidak terikat tahun tertentu).
     */
    /* public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }
    */

    public function siswa(): BelongsToMany
    {
        /**
         * Karena tahun_ajaran_id ada di tabel pivot 'siswa_kelas',
         * kita tetap bisa mengakses informasi tahun ajaran lewat relasi ini.
         */
        return $this->belongsToMany(Siswa::class, 'siswa_kelas', 'kelas_id', 'siswa_id')
                    ->withPivot('is_active', 'tahun_ajaran_id')
                    ->withTimestamps();
    }

    public function guruMapel(): HasMany
    {
        return $this->hasMany(GuruMapel::class, 'kelas_id');
    }
}