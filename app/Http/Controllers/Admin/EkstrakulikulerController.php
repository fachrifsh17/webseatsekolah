<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Ekstrakulikuler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EkstrakulikulerController extends Controller {
  public function index(){ $data = Ekstrakulikuler::paginate(12); return view('admin.ekskul.index',compact('data')); }
  public function create(){ return view('admin.ekskul.create'); }
  public function store(Request $r){
    $r->validate(['nama_ekskul'=>'required']);
    $foto = $r->hasFile('foto') ? $r->file('foto')->store('uploads/ekskul','public') : null;
    Ekstrakulikuler::create($r->only(['nama_ekskul','deskripsi','hari','jam_mulai','jam_selesai','pembina_id','keterangan']) + ['foto'=>$foto]);
    return redirect()->route('admin.ekskul.index')->with('ok','Ekskul dibuat');
  }
  public function edit($id){ $item = Ekstrakulikuler::findOrFail($id); return view('admin.ekskul.edit',compact('item')); }
  public function update(Request $r,$id){
    $item = Ekstrakulikuler::findOrFail($id);
    $r->validate(['nama_ekskul'=>'required']);
    if($r->hasFile('foto')){ if($item->foto) Storage::disk('public')->delete($item->foto); $item->foto = $r->file('foto')->store('uploads/ekskul','public'); }
    $item->update($r->only(['nama_ekskul','deskripsi','hari','jam_mulai','jam_selesai','pembina_id','keterangan']) + ['foto'=>$item->foto]);
    return redirect()->route('admin.ekskul.index')->with('ok','Ekskul diubah');
  }
  public function destroy($id){ $item = Ekstrakulikuler::findOrFail($id); if($item->foto) Storage::disk('public')->delete($item->foto); $item->delete(); return redirect()->route('admin.ekskul.index')->with('ok','Ekskul dihapus'); }
}
