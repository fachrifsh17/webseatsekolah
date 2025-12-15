<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KurikulumController extends Controller {
  public function index(){ $data = Kurikulum::paginate(12); return view('admin.kurikulum.index',compact('data')); }
  public function create(){ return view('admin.kurikulum.create'); }
  public function store(Request $r){
    $r->validate(['judul'=>'required']);
    $file = $r->hasFile('file_jadwal') ? $r->file('file_jadwal')->store('uploads/kurikulum','public') : null;
    Kurikulum::create(['judul'=>$r->judul,'penjelasan_kurikulum'=>$r->penjelasan_kurikulum,'file_jadwal_path'=>$file]);
    return redirect()->route('admin.kurikulum.index')->with('ok','Kurikulum dibuat');
  }
  public function edit($id){ $item = Kurikulum::findOrFail($id); return view('admin.kurikulum.edit',compact('item')); }
  public function update(Request $r,$id){
    $item = Kurikulum::findOrFail($id);
    $r->validate(['judul'=>'required']);
    if($r->hasFile('file_jadwal')){ if($item->file_jadwal_path) Storage::disk('public')->delete($item->file_jadwal_path); $item->file_jadwal_path = $r->file('file_jadwal')->store('uploads/kurikulum','public'); }
    $item->judul = $r->judul; $item->penjelasan_kurikulum = $r->penjelasan_kurikulum; $item->save();
    return redirect()->route('admin.kurikulum.index')->with('ok','Kurikulum diubah');
  }
  public function destroy($id){ $item = Kurikulum::findOrFail($id); if($item->file_jadwal_path) Storage::disk('public')->delete($item->file_jadwal_path); $item->delete(); return redirect()->route('admin.kurikulum.index')->with('ok','Kurikulum dihapus'); }
}
