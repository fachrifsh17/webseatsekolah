<?php // app/Models/DataKontak.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DataKontak extends Model {
    protected $table = 'data_kontak';
    protected $fillable = ['alamat_lengkap','telepon','email_resmi','peta_embed_code'];
    public $timestamps = false;
    public $incrementing = false;
}
