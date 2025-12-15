<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use Illuminate\Http\Request;

class StrukturController extends Controller {
  public function index(){ $data = StrukturJabatan::with('guru')->orderBy('urutan_tampil')->get(); return view('admin.struktur.index',compact('data')); }
  public function create(){ return view('admin.struktur.create'); }
  public function store(Request $r){ $r->validate(['nama_jabatan_struktural'=>'required']); StrukturJabatan::create($r->only(['guru_staf_id','nama_jabatan_struktural','periode_mulai','urutan_tampil'])); return redirect()->route('admin.struktur.index')->with('ok','Struktur ditambahkan'); }
  public function edit($id){ $item = StrukturJabatan::findOrFail($id); return view('admin.struktur.edit',compact('item')); }
  public function update(Request $r,$id){ $item = StrukturJabatan::findOrFail($id); $r->validate(['nama_jabatan_struktural'=>'required']); $item->update($r->only(['guru_staf_id','nama_jabatan_struktural','periode_mulai','urutan_tampil'])); return redirect()->route('admin.struktur.index')->with('ok','Struktur diubah'); }
  public function destroy($id){ StrukturJabatan::findOrFail($id)->delete(); return redirect()->route('admin.struktur.index')->with('ok','Struktur dihapus'); }
}
