<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kurikulum extends Model
{
    use HasFactory;

    protected $table = 'kurikulum';

    protected $fillable = [
        'judul',
        'penjelasan_kurikulum',
        'file_jadwal_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $timestamps = true;

    public function tahunAjaran(): HasMany
    {
        return $this->hasMany(TahunAjaran::class, 'kurikulum_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}