<?php // app/Models/Role.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Role extends Model {
    protected $table = 'roles';
    protected $fillable = ['role_name','description'];
    public $timestamps = false;

    public function admins() { return $this->hasMany(User::class,'role_id'); }
}
