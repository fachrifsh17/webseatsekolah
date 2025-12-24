<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'username', 
        'password', 
        'nama_lengkap', 
        'role_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'integer',
        'password' => 'hashed',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function guru(): HasOne
    {
        return $this->hasOne(GuruStaf::class, 'user_id');
    }


    public function siswa(): HasOne
    {
        return $this->hasOne(Siswa::class, 'user_id');
    }


    public function orangtua(): HasOne
    {
        return $this->hasOne(OrangTua::class, 'user_id');
    }
}