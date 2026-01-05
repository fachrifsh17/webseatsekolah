<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'role_name',
        'description',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')->withTimestamps();
    }

    public function hasUser($user): bool
    {
        $id = $user instanceof User ? $user->id : (int) $user;
        if ($this->relationLoaded('users')) {
            return $this->users->contains('id', $id);
        }
        return $this->users()->where('users.id', $id)->exists();
    }

    public function scopeByName($query, string $name)
    {
        return $query->where('role_name', $name);
    }
}
