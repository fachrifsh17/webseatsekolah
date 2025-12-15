<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller {
  public function index(){ $data = Banner::orderByDesc('aktif_sampai')->paginate(12); return view('admin.banner.index',compact('data')); }
  public function create(){ return view('admin.banner.create'); }
  public function store(Request $r){
    $r->validate(['judul'=>'required']);
    $foto = $r->hasFile('foto') ? $r->file('foto')->store('uploads/banner','public') : null;
    Banner::create(['judul'=>$r->judul,'url_link'=>$r->url_link,'aktif_sampai'=>$r->aktif_sampai,'foto'=>$foto]);
    return redirect()->route('admin.banner.index')->with('ok','Banner dibuat');
  }
  public function edit($id){ $item = Banner::findOrFail($id); return view('admin.banner.edit',compact('item')); }
  public function update(Request $r,$id){
    $item = Banner::findOrFail($id);
    $r->validate(['judul'=>'required']);
    if($r->hasFile('foto')){ if($item->foto) Storage::disk('public')->delete($item->foto); $item->foto = $r->file('foto')->store('uploads/banner','public'); }
    $item->judul = $r->judul; $item->url_link = $r->url_link; $item->aktif_sampai = $r->aktif_sampai; $item->save();
    return redirect()->route('admin.banner.index')->with('ok','Banner diubah');
  }
  public function destroy($id){ $item = Banner::findOrFail($id); if($item->foto) Storage::disk('public')->delete($item->foto); $item->delete(); return redirect()->route('admin.banner.index')->with('ok','Banner dihapus'); }
}
