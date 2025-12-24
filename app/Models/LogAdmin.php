<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAdmin extends Model
{
    use HasFactory;
    protected $table = 'log_admin';
    public $timestamps = true; 
    protected $fillable = [
        'user_id',
        'aksi',
        'ip_address',
        'user_agent',
    ];
    
}