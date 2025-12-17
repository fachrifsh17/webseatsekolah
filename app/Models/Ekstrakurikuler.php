<?php // app/Models/Ekstrakurikuler.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Ekstrakurikuler extends Model {
    protected $table = 'ekstrakurikuler';
    protected $fillable = ['nama_ekskul','deskripsi','hari','jam_mulai','jam_selesai','pembina_id','foto','keterangan'];
    public $timestamps = false;

    public function pembina() { return $this->belongsTo(GuruStaf::class,'pembina_id'); }
}
