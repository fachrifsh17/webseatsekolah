<?php // app/Models/BannerPengumuman.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model {
    protected $table = 'banner_pengumuman';
    protected $fillable = ['judul','url_link','aktif_sampai','foto'];
    public $timestamps = false;
}
