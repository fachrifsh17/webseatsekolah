<?php // app/Models/AuthToken.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AuthToken extends Model {
    protected $table = 'auth_tokens';
    protected $fillable = ['token_hash','user_id','expires_at','revoked','created_at'];
    public $timestamps = false;

    public function user() { return $this->belongsTo(User::class,'user_id'); }
}
