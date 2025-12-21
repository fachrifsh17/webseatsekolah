<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ekstrakurikuler extends Model
{
    use HasFactory;

    protected $table = 'ekstrakurikuler';

    protected $fillable = [
        'nama_ekskul',
        'deskripsi',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'pembina_id',
        'foto',
        'keterangan',
    ];

    public function pembina(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'pembina_id');
    }
}