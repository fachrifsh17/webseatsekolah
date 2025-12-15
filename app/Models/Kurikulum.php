<?php // app/Models/Kurikulum.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Kurikulum extends Model {
    protected $table = 'kurikulum';
    protected $fillable = ['judul','penjelasan_kurikulum','file_jadwal_path'];
    public $timestamps = false;
}
