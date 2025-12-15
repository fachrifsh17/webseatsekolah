<?php // app/Models/GuruMapel.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class GuruMapel extends Model {
    protected $table = 'guru_mapel';
    protected $fillable = ['guru_staf_id','mata_pelajaran_id'];
    public $timestamps = false;

    public function guru() { return $this->belongsTo(GuruStaf::class,'guru_staf_id'); }
    public function mapel() { return $this->belongsTo(MataPelajaran::class,'mata_pelajaran_id'); }
}
