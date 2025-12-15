<?php // app/Models/Jurusan.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Jurusan extends Model {
    protected $table = 'jurusan';
    protected $fillable = ['nama_jurusan','deskripsi','foto'];
    public $timestamps = false;
}
