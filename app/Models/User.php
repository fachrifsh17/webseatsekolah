<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;

<<<<<<< HEAD
=======

>>>>>>> master
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'username',
        'password',
        'nama_lengkap',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'id'           => 'string',
        'username'     => 'string',
        'nama_lengkap' => 'string',
        'is_active'    => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastId = static::orderBy('id', 'desc')->first()?->id;
                $num = $lastId ? (int) substr($lastId, 1) + 1 : 1;
                $model->id = 'U' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

<<<<<<< HEAD
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withTimestamps();
    }

=======
>>>>>>> master
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value) 
            ? Hash::make($value) 
            : $value;
    }

<<<<<<< HEAD
=======
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withTimestamps();
    }

>>>>>>> master
    protected function getNormalizedRoleNames(): array
    {
        return $this->roles->pluck('role_name')->map(fn($role) => strtolower($role))->all();
    }

    public function hasRole(string $roleName): bool
    {
        return in_array(strtolower($roleName), $this->getNormalizedRoleNames(), true);
    }

    public function hasAnyRole($roles): bool
    {
        $rolesArr = is_string($roles) ? array_map('trim', explode(',', $roles)) : (array) $roles;
        $lower = array_map('strtolower', $rolesArr);

        return (bool) array_intersect($lower, $this->getNormalizedRoleNames());
    }

    public function getRoleNames(): array
    {
        return $this->roles->pluck('role_name')->all();
    }

    public function guruStaf(): HasOne
    {
        return $this->hasOne(GuruStaf::class, 'user_id', 'id');
    }

    public function siswa(): HasOne
    {
        return $this->hasOne(Siswa::class, 'user_id', 'id');
    }

    public function orangtua(): HasOne
    {
        return $this->hasOne(OrangTua::class, 'user_id', 'id');
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> master
