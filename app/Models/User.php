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

    protected $fillable = [
        'username',
        'password',
        'nama_lengkap',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')->withTimestamps();
    }

    public function setPasswordAttribute(string $value): void
    {
        $info = password_get_info($value);
        // Jika value bukan hash (algo = 0), maka hash; jika sudah hash, simpan apa adanya
        if (empty($info['algo'])) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }

    protected function getNormalizedRoleNames(): array
    {
        $names = $this->relationLoaded('roles')
            ? $this->roles->pluck('role_name')->all()
            : $this->roles()->pluck('role_name')->all();

        if (empty($names)) {
            return [];
        }

        return array_map(fn($r) => strtolower((string) $r), $names);
    }

    public function hasRole(string $roleName): bool
    {
        return in_array(strtolower($roleName), $this->getNormalizedRoleNames(), true);
    }

    /**
     * @param string|array $roles
     */
    public function hasAnyRole($roles): bool
    {
        $rolesArr = is_string($roles) ? array_map('trim', explode(',', $roles)) : (array) $roles;
        $lower = array_map('strtolower', $rolesArr);

        return (bool) array_intersect($lower, $this->getNormalizedRoleNames());
    }

    /**
     * @param string|array $roles
     */
    public function hasAllRoles($roles): bool
    {
        $rolesArr = is_string($roles) ? array_map('trim', explode(',', $roles)) : (array) $roles;
        $lower = array_map('strtolower', $rolesArr);

        $userRoles = $this->getNormalizedRoleNames();

        return empty(array_diff($lower, $userRoles));
    }

    public function getRoleNames(): array
    {
        return $this->relationLoaded('roles')
            ? $this->roles->pluck('role_name')->map(fn($r) => (string) $r)->all()
            : $this->roles()->pluck('role_name')->map(fn($r) => (string) $r)->all();
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
