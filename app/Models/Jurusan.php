<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jurusan extends Model
{
    use HasFactory;

    protected $table = 'jurusan';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nama_jurusan',
        'deskripsi',
        'foto',
        'is_active', // <-- Tambahkan ini agar bisa di-input/update
    ];

    // Opsional: Cast is_active agar otomatis jadi boolean di Laravel
    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastId = static::max('id');
                $num = $lastId ? (int) substr($lastId, 1) + 1 : 1;
                $model->id = 'J' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }

            // Set default true jika saat create tidak diisi
            if (!isset($model->is_active)) {
                $model->is_active = true;
            }
        });
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'jurusan_id');
    }

    public function guruStaf(): HasMany
    {
        return $this->hasMany(GuruStaf::class, 'jurusan_id');
    }
}