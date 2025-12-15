<?php // app/Models/SekolahSeting.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SekolahSetting extends Model {
    protected $table = 'sekolah_seting';
    protected $fillable = ['tagline','logo','pesan_selamat_datang'];
    public $timestamps = false;
    public $incrementing = false;
}
