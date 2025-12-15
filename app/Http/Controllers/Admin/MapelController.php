<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use Illuminate\Http\Request;

class MapelController extends Controller {
  public function index(){ $data = MataPelajaran::paginate(12); return view('admin.mapel.index',compact('data')); }
  public function create(){ return view('admin.mapel.create'); }
  public function store(Request $r){ $r->validate(['nama_mapel'=>'required']); MataPelajaran::create($r->only(['nama_mapel','jurusan_id','tipe_mapel','kategori_mapel'])); return redirect()->route('admin.mapel.index')->with('ok','Mapel dibuat'); }
  public function edit($id){ $item = MataPelajaran::findOrFail($id); return view('admin.mapel.edit',compact('item')); }
  public function update(Request $r,$id){ $item = MataPelajaran::findOrFail($id); $r->validate(['nama_mapel'=>'required']); $item->update($r->only(['nama_mapel','jurusan_id','tipe_mapel','kategori_mapel'])); return redirect()->route('admin.mapel.index')->with('ok','Mapel diubah'); }
  public function destroy($id){ MataPelajaran::findOrFail($id)->delete(); return redirect()->route('admin.mapel.index')->with('ok','Mapel dihapus'); }
}
