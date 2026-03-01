<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JamSekolah extends Model
{
    use HasFactory;

    protected $table = 'jam_sekolah';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'semester_id', // --- PERUBAHAN DI SINI ---
        'hari',
        'jam_ke',
        'waktu_mulai',
        'waktu_selesai',
        'jenis',
        'keterangan',
    ];

    protected $casts = [
        'jam_ke'          => 'integer',
        'waktu_mulai'     => 'datetime:H:i',
        'waktu_selesai'   => 'datetime:H:i',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastId = static::orderBy('id', 'desc')->value('id');
                // Asumsi format ID adalah JM001
                $num = $lastId ? (int) substr($lastId, 2) + 1 : 1;
                $model->id = 'JM' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    // --- PERUBAHAN DI SINI ---
    // Nama fungsi dan model yang dirujuk diubah ke Semester
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'id');
    }
}