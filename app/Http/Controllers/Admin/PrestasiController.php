<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use Illuminate\Http\Request;

class PrestasiController extends Controller
{
    public function __construct() { $this->middleware('auth:admin'); }

    public function index()
    {
        $items = Prestasi::orderBy('tahun','desc')->paginate(12);
        return view('admin.prestasi.index', compact('items'));
    }

    public function create() { return view('admin.prestasi.create'); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'judul'=>'required|string|max:255',
            'tahun'=>'nullable|digits:4|integer',
            'tingkat'=>'nullable|string|max:50',
            'kategori'=>'nullable|in:Siswa,Sekolah',
            'foto'=>'nullable|image|max:2048'
        ]);
        if ($request->hasFile('foto')) $data['foto'] = $request->file('foto')->store('prestasi','public');
        Prestasi::create($data);
        return redirect()->route('admin.prestasi.index')->with('success','Prestasi ditambahkan');
    }

    public function edit(Prestasi $prestasi) { return view('admin.prestasi.edit', compact('prestasi')); }

    public function update(Request $request, Prestasi $prestasi)
    {
        $data = $request->validate([
            'judul'=>'required|string|max:255',
            'tahun'=>'nullable|digits:4|integer',
            'tingkat'=>'nullable|string|max:50',
            'kategori'=>'nullable|in:Siswa,Sekolah',
            'foto'=>'nullable|image|max:2048'
        ]);
        if ($request->hasFile('foto')) $data['foto'] = $request->file('foto')->store('prestasi','public');
        $prestasi->update($data);
        return redirect()->route('admin.prestasi.index')->with('success','Prestasi diperbarui');
    }

    public function destroy(Prestasi $prestasi)
    {
        $prestasi->delete();
        return redirect()->route('admin.prestasi.index')->with('success','Prestasi dihapus');
    }
}
