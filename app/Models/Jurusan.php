<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jurusan extends Model
{
    use HasFactory;

    protected $table = 'jurusan';

    protected $fillable = [
        'nama_jurusan',
        'deskripsi',
        'foto',
    ];

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'jurusan_id');
    }

    public function guruStaf(): HasMany
    {
        return $this->hasMany(GuruStaf::class, 'jurusan_id');
    }
}