<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengumuman extends Model
{
    use HasFactory;

    // Nama tabel (opsional, kalau sesuai konvensi bisa diabaikan)
    protected $table = 'pengumuman';

    // Kolom yang bisa diisi mass-assignment
    protected $fillable = [
        'judul',
        'isi_pengumuman',
        'tanggal_publikasi',
        'penting',
    ];

    // Casting tipe data
    protected $casts = [
        'tanggal_publikasi' => 'datetime',
        'penting' => 'boolean',
    ];

    /**
     * Relasi ke user admin yang membuat pengumuman
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
