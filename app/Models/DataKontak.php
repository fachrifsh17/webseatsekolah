<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataKontak extends Model
{
    use HasFactory;

    protected $table = 'data_kontak';

    protected $fillable = [
        'alamat_lengkap',
        'telepon',
        'email_resmi',
        'peta_embed_code',
    ];

    public $incrementing = false;
}