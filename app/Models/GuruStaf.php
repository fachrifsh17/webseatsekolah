<?php // app/Models/GuruStaf.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class GuruStaf extends Model {
    protected $table = 'guru_staf';
    protected $fillable = ['nip','nuptk','nama','jabatan_fungsional','status_kepegawaian','foto','jurusan_id'];
    public $timestamps = false;

    public function jurusan() { return $this->belongsTo(Jurusan::class,'jurusan_id'); }
}
