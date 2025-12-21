<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuruStaf extends Model
{
    use HasFactory;

    protected $table = 'guru_staf';

    protected $fillable = [
        'user_id',
        'nip',
        'nuptk',
        'nama',
        'jabatan_fungsional',
        'status_kepegawaian',
        'foto',
        'jurusan_id'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }

    public function guruMapel(): HasMany
    {
        return $this->hasMany(GuruMapel::class, 'guru_staf_id');
    }
}