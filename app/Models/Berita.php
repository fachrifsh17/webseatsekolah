<?php // app/Models/Berita.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Berita extends Model {
    protected $table = 'berita';
    protected $fillable = ['judul','isi_berita','tanggal_publikasi','foto'];
    public $timestamps = false;
}
