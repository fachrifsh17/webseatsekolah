<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\PortalSosmed;
use Illuminate\Http\Request;

class PortalController extends Controller {
  public function index(){ $data = PortalSosmed::paginate(12); return view('admin.portal.index',compact('data')); }
  public function create(){ return view('admin.portal.create'); }
  public function store(Request $r){ $r->validate(['nama_platform'=>'required']); PortalSosmed::create($r->only(['nama_platform','url_link','tipe'])); return redirect()->route('admin.portal.index')->with('ok','Portal ditambahkan'); }
  public function edit($id){ $item = PortalSosmed::findOrFail($id); return view('admin.portal.edit',compact('item')); }
  public function update(Request $r,$id){ $item = PortalSosmed::findOrFail($id); $r->validate(['nama_platform'=>'required']); $item->update($r->only(['nama_platform','url_link','tipe'])); return redirect()->route('admin.portal.index')->with('ok','Portal diubah'); }
  public function destroy($id){ PortalSosmed::findOrFail($id)->delete(); return redirect()->route('admin.portal.index')->with('ok','Portal dihapus'); }
}
