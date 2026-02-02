<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'role_name',
        'description',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastId = static::max('id');
                $num = $lastId ? (int) substr($lastId, 1) + 1 : 1;
                $model->id = 'R' . str_pad($num, 3, '0', STR_PAD_LEFT);
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')->withTimestamps();
    }

    public function hasUser($user): bool
    {
        $id = $user instanceof User ? $user->id : $user;
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
