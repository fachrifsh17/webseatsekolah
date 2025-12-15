<?php // app/Models/LogAdmin.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LogAdmin extends Model {
    protected $table = 'user';
    protected $fillable = ['user_id','aksi','created_at'];
    public $timestamps = false;

    public function user() { return $this->belongsTo(User::class,'user_id'); }
}
