<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataKontak extends Model
{
    use HasFactory;

    protected $table = 'data_kontak';

    protected $fillable = [
        // Kolom alamat yang sudah dipecah
        'alamat_jalan',
        'desa_kelurahan',
        'kecamatan',
        'kabupaten_kota',
        'provinsi',
        
        // Kolom lainnya tetap dipertahankan
        'telepon',
        'email_resmi',
        'peta_embed_code',
    ];

    public $incrementing = true;

    // Tetap gunakan settingan awal kamu
    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';
}