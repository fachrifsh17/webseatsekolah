<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tingkatan extends Model
{
    use HasFactory;

    // Tentukan nama tabel
    protected $table = 'tingkatan';

    // Tentukan primary key (default-nya 'id', jadi baris ini bisa dihapus jika mau)
    protected $primaryKey = 'id';
    
    // --- PENYESUAIAN DI SINI ---
    // Karena id sudah INT auto-increment, keyType adalah 'int'
    protected $keyType = 'int';
    
    // --- PENYESUAIAN DI SINI ---
    // Aktifkan auto-increment
    public $incrementing = true;

    // Tentukan field yang boleh diisi (mass assignable)
    protected $fillable = [
        // 'id' tidak perlu dimasukkan di sini jika auto-increment
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