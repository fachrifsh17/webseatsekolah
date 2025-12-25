<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'is_active',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function hasRole($roleName)
    {
        return $this->roles->contains('role_name', $roleName);
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