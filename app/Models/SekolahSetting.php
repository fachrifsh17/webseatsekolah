<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SekolahSetting extends Model
{
    use HasFactory;

    protected $table = 'sekolah_setting';

    protected $fillable = [
        'tagline',
        'pesan_selamat_datang',
        'buku_poin_path',
        'no_wa_kesiswaan',
    ];

    // hanya pakai updated_at, disable created_at
    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';
}