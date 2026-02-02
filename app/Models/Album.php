<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use HasFactory;

    protected $table = 'album';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nama_album',
        'tanggal_kegiatan',
        'cover_path',
    ];

    protected $casts = [
        'tanggal_kegiatan' => 'date:Y-m-d',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastId = static::max('id');
                $num = $lastId ? (int) substr($lastId, 1) + 1 : 1;
                $model->id = 'A' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'album_id');
    }
}
