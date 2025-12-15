<?php // app/Models/Prestasi.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Prestasi extends Model {
    protected $table = 'prestasi';
    protected $fillable = ['judul','tahun','tingkat','kategori','foto'];
    public $timestamps = false;
}
