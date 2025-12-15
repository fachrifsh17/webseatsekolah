<?php // app/Models/PPDBLink.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PPDBLink extends Model {
    protected $table = 'ppdb_link';
    protected $fillable = ['url_link','status_ppdb'];
    public $timestamps = false;
    public $incrementing = false;
}
