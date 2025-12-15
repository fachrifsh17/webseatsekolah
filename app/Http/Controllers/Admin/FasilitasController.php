<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FasilitasController extends Controller {
  public function index(){ $data = Fasilitas::paginate(12); return view('admin.fasilitas.index',compact('data')); }
  public function create(){ return view('admin.fasilitas.create'); }
  public function store(Request $r){
    $r->validate(['nama_fasilitas'=>'required']);
    $foto = $r->hasFile('foto') ? $r->file('foto')->store('uploads/fasilitas','public') : null;
    Fasilitas::create(['nama_fasilitas'=>$r->nama_fasilitas,'foto'=>$foto,'keterangan'=>$r->keterangan]);
    return redirect()->route('admin.fasilitas.index')->with('ok','Fasilitas dibuat');
  }
  public function edit($id){ $item = Fasilitas::findOrFail($id); return view('admin.fasilitas.edit',compact('item')); }
  public function update(Request $r,$id){
    $item = Fasilitas::findOrFail($id);
    $r->validate(['nama_fasilitas'=>'required']);
    if($r->hasFile('foto')){ if($item->foto) Storage::disk('public')->delete($item->foto); $item->foto = $r->file('foto')->store('uploads/fasilitas','public'); }
    $item->nama_fasilitas = $r->nama_fasilitas; $item->keterangan = $r->keterangan; $item->save();
    return redirect()->route('admin.fasilitas.index')->with('ok','Fasilitas diubah');
  }
  public function destroy($id){ $item = Fasilitas::findOrFail($id); if($item->foto) Storage::disk('public')->delete($item->foto); $item->delete(); return redirect()->route('admin.fasilitas.index')->with('ok','Fasilitas dihapus'); }
}
