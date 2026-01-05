<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pesan extends Model
{
    use HasFactory;

    protected $table = 'pesan_masuk';

    public $timestamps = false;

    protected $fillable = [
        'nama_lengkap',
        'email',
        'subjek',
        'isi_pesan',
        'status',
        'tanggal_kirim',
    ];

    protected $casts = [
        'status' => 'string',
        'tanggal_kirim' => 'date:Y-m-d',
    ];
}
