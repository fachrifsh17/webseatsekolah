<?php // app/Models/MataPelajaran.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MataPelajaran extends Model {
    protected $table = 'mata_pelajaran';
    protected $fillable = ['nama_mapel','jurusan_id','tipe_mapel','kategori_mapel'];
    public $timestamps = false;

    public function jurusan() { return $this->belongsTo(Jurusan::class,'jurusan_id'); }
}
