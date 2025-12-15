<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Jurusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JurusanController extends Controller {
  public function index(){ $data = Jurusan::paginate(12); return view('admin.jurusan.index',compact('data')); }
  public function create(){ return view('admin.jurusan.create'); }
  public function store(Request $r){
    $r->validate(['nama_jurusan'=>'required']);
    $foto = $r->hasFile('foto') ? $r->file('foto')->store('uploads/jurusan','public') : null;
    Jurusan::create(['nama_jurusan'=>$r->nama_jurusan,'deskripsi'=>$r->deskripsi,'foto'=>$foto]);
    return redirect()->route('admin.jurusan.index')->with('ok','Jurusan dibuat');
  }
  public function edit($id){ $item = Jurusan::findOrFail($id); return view('admin.jurusan.edit',compact('item')); }
  public function update(Request $r,$id){
    $item = Jurusan::findOrFail($id);
    $r->validate(['nama_jurusan'=>'required']);
    if($r->hasFile('foto')){ if($item->foto) Storage::disk('public')->delete($item->foto); $item->foto = $r->file('foto')->store('uploads/jurusan','public'); }
    $item->nama_jurusan = $r->nama_jurusan; $item->deskripsi = $r->deskripsi; $item->save();
    return redirect()->route('admin.jurusan.index')->with('ok','Jurusan diubah');
  }
  public function destroy($id){ $item = Jurusan::findOrFail($id); if($item->foto) Storage::disk('public')->delete($item->foto); $item->delete(); return redirect()->route('admin.jurusan.index')->with('ok','Jurusan dihapus'); }
}
