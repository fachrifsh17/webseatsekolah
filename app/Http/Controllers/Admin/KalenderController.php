<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use Illuminate\Http\Request;

class KalenderController extends Controller {
  public function index(){ $data = KalenderAkademik::paginate(12); return view('admin.kalender.index',compact('data')); }
  public function create(){ return view('admin.kalender.create'); }
  public function store(Request $r){ $r->validate(['kegiatan'=>'required']); KalenderAkademik::create($r->only(['kegiatan','tanggal_mulai','tanggal_selesai','kategori'])); return redirect()->route('admin.kalender.index')->with('ok','Kegiatan ditambahkan'); }
  public function edit($id){ $item = KalenderAkademik::findOrFail($id); return view('admin.kalender.edit',compact('item')); }
  public function update(Request $r,$id){ $item = KalenderAkademik::findOrFail($id); $r->validate(['kegiatan'=>'required']); $item->update($r->only(['kegiatan','tanggal_mulai','tanggal_selesai','kategori'])); return redirect()->route('admin.kalender.index')->with('ok','Kegiatan diubah'); }
  public function destroy($id){ KalenderAkademik::findOrFail($id)->delete(); return redirect()->route('admin.kalender.index')->with('ok','Kegiatan dihapus'); }
}
