<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAdmin extends Model
{
    use HasFactory;

    // Pastikan sesuai dengan nama tabel di database
    protected $table = 'log_admin';

    // Jika tabel punya kolom created_at & updated_at biarkan true, kalau tidak ada set false
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'aksi',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
