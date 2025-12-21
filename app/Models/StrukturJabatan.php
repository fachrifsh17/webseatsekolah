<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrukturJabatan extends Model
{
    use HasFactory;

    protected $table = 'struktur_jabatan';

    protected $fillable = [
        'guru_staf_id',
        'nama_jabatan_struktural',
        'periode_mulai',
        'urutan_tampil',
    ];

    protected $casts = [
        'urutan_tampil' => 'integer',
        'periode_mulai' => 'date',
    ];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id');
    }
}