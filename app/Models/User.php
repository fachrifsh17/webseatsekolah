<?php // app/Models/UserAdmin.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class User extends Model {
    protected $table = 'users';
    protected $fillable = ['username','password','nama_lengkap','role_id','created_at'];
    public $timestamps = false;

    public function role() { return $this->belongsTo(Role::class,'role_id'); }
}
