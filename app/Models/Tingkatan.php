<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tingkatan extends Model
{
    // Tentukan nama tabel
    protected $table = 'tingkatan';

    // Tentukan primary key jika tidak menggunakan 'id' auto-increment
    protected $primaryKey = 'id';
    
    // Tentukan tipe data primary key (karena kita pakai string/varchar)
    protected $keyType = 'string';
    
    // Nonaktifkan auto-increment untuk primary key string
    public $incrementing = false;

    // Tentukan field yang boleh diisi (mass assignable)
    protected $fillable = [
        'id',
        'nama_tingkatan',
    ];

    /**
     * Relasi ke model Kelas
     * Satu tingkatan memiliki banyak kelas
     */
    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'tingkatan_id', 'id');
    }
}