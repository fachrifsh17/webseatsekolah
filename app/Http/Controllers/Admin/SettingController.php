<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\SekolahSetting;
use App\Models\DataKontak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller {
  public function index(){
    $setting = SekolahSetting::first();
    $kontak = DataKontak::first();
    return view('admin.setting.index', compact('setting','kontak'));
  }

  public function updateGeneral(Request $r){
    $s = SekolahSetting::first() ?? new SekolahSetting(['id'=>1]);
    if($r->hasFile('logo')){ if($s->logo) Storage::disk('public')->delete($s->logo); $s->logo = $r->file('logo')->store('uploads/logo','public'); }
    $s->tagline = $r->tagline; $s->pesan_selamat_datang = $r->pesan_selamat_datang; $s->save();
    return redirect()->back()->with('ok','Setting umum disimpan');
  }

  public function updateKontak(Request $r){
    $k = DataKontak::first() ?? new DataKontak(['id'=>1]);
    $k->alamat_lengkap = $r->alamat_lengkap; $k->telepon = $r->telepon; $k->email_resmi = $r->email_resmi; $k->peta_embed_code = $r->peta_embed_code; $k->save();
    return redirect()->back()->with('ok','Kontak disimpan');
  }
}
