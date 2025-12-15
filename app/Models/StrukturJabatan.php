<?php // app/Models/StrukturJabatan.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class StrukturJabatan extends Model {
    protected $table = 'struktur_jabatan';
    protected $fillable = ['guru_staf_id','nama_jabatan_struktural','periode_mulai','urutan_tampil'];
    public $timestamps = false;

    public function guru() { return $this->belongsTo(GuruStaf::class,'guru_staf_id'); }
}
