<?php // app/Models/Media.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Media extends Model {
    protected $table = 'media';
    protected $fillable = ['album_id','media_path','jenis_media','keterangan'];
    public $timestamps = false;

    public function album() { return $this->belongsTo(Album::class,'album_id'); }
}
