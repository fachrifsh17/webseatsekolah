<?php // app/Models/Fasilitas.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Fasilitas extends Model {
    protected $table = 'fasilitas';
    protected $fillable = ['nama_fasilitas','foto','keterangan'];
    public $timestamps = false;
}
