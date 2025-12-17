<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'username', 
        'password', 
        'nama_lengkap', 
        'role_id',
        'api_token' 
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public $timestamps = true; 

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}