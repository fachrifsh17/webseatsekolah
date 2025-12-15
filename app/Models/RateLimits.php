<?php // app/Models/RateLimits.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RateLimits extends Model {
    protected $table = 'rate_limits';
    protected $fillable = ['key_name','attempts','last_attempt','created_at'];
    public $timestamps = false;
}
