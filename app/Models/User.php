<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;

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
        // 'nama_lengkap', // DIHAPUS karena tidak ada di tabel users
        'current_role',    // TETAP ADA sesuai struktur DB Anda
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'id'           => 'string',
        'username'     => 'string',
        // 'nama_lengkap' => 'string', // DIHAPUS
        'current_role' => 'string',
        'is_active'    => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                // Logic custom ID U001, U002, dst.
                $lastId = static::orderBy('id', 'desc')->first()?->id;
                $num = $lastId ? (int) substr($lastId, 1) + 1 : 1;
                $model->id = 'U' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Otomatis melakukan hashing password jika diisi secara plain text
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value) 
            ? Hash::make($value) 
            : $value;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withTimestamps();
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
        return $this->hasOne(Orangtua::class, 'user_id', 'id');
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE & PERMISSION LOGIC
    |--------------------------------------------------------------------------
    */

    protected function getNormalizedRoleNames(): array
    {
        return $this->roles->pluck('role_name')->map(fn($role) => strtolower($role))->all();
    }

    /**
     * Cek kepemilikan role di tabel pivot
     */
    public function hasRole(string $roleName): bool
    {
        return in_array(strtolower($roleName), $this->getNormalizedRoleNames(), true);
    }

    /**
     * Cek role yang sedang aktif (current_role)
     */
    public function isCurrently(string $roleName): bool
    {
        return strtolower($this->current_role) === strtolower($roleName);
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
}