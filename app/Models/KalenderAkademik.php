<?php // app/Models/KalenderAkademik.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class KalenderAkademik extends Model {
    protected $table = 'kalender_akademik';
    protected $fillable = ['kegiatan','tanggal_mulai','tanggal_selesai','kategori'];
    public $timestamps = false;
}
