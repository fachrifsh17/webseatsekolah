<?php // app/Models/ProfilSekolah.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProfilSekolah extends Model {
    protected $table = 'profil_sekolah';
    protected $fillable = ['sejarah','visi','misi','npsn','akreditasi','sambutan_kepsek'];
    public $timestamps = false;
    public $incrementing = false; // pk id tanpa auto inc di SQL-mu
}
