<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\GuruStaf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GuruController extends Controller {
  public function index(){ $data = GuruStaf::paginate(12); return view('admin.guru.index',compact('data')); }
  public function create(){ return view('admin.guru.create'); }
  public function store(Request $r){
    $r->validate(['nama'=>'required']);
    $foto = $r->hasFile('foto') ? $r->file('foto')->store('uploads/guru','public') : null;
    GuruStaf::create($r->only(['nip','nuptk','nama','jabatan_fungsional','status_kepegawaian','jurusan_id']) + ['foto'=>$foto]);
    return redirect()->route('admin.guru.index')->with('ok','Guru/Staf dibuat');
  }
  public function edit($id){ $item = GuruStaf::findOrFail($id); return view('admin.guru.edit',compact('item')); }
  public function update(Request $r,$id){
    $item = GuruStaf::findOrFail($id);
    $r->validate(['nama'=>'required']);
    if($r->hasFile('foto')){ if($item->foto) Storage::disk('public')->delete($item->foto); $item->foto = $r->file('foto')->store('uploads/guru','public'); }
    $item->update($r->only(['nip','nuptk','nama','jabatan_fungsional','status_kepegawaian','jurusan_id']) + ['foto'=>$item->foto]);
    return redirect()->route('admin.guru.index')->with('ok','Guru/Staf diubah');
  }
  public function destroy($id){ $item = GuruStaf::findOrFail($id); if($item->foto) Storage::disk('public')->delete($item->foto); $item->delete(); return redirect()->route('admin.guru.index')->with('ok','Guru/Staf dihapus'); }
}
