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
        'jabatan_id',
        'periode_mulai',
        'urutan_tampil',
    ];

    protected $casts = [
        'urutan_tampil' => 'integer',
        'periode_mulai' => 'date',
    ];

    public function guru(): BelongsTo // Mengubah nama method agar sinkron dengan Controller (->with('guru'))
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id', 'id');
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id', 'id');
    }

    public function guruStaf(): BelongsTo
    {
        return $this->guru();
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}