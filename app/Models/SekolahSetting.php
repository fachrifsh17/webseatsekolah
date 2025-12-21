<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SekolahSetting extends Model
{
    use HasFactory;

    protected $table = 'sekolah_seting';

    protected $fillable = [
        'tagline',
        'logo',
        'pesan_selamat_datang',
    ];
    public $incrementing = false;
}