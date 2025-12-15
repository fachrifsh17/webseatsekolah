<?php // app/Models/PortalSosmed.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PortalSosmed extends Model {
    protected $table = 'portal_sosmed';
    protected $fillable = ['nama_platform','url_link','tipe'];
    public $timestamps = false;
}
