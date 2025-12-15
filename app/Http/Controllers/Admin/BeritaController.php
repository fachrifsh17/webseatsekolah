<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use Illuminate\Http\Request;

class BeritaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin'); // sesuaikan guard jika perlu
    }

    public function index()
    {
        $beritas = Berita::orderBy('tanggal_publikasi','desc')->paginate(10);
        return view('admin.berita.index', compact('beritas'));
    }

    public function create()
    {
        return view('admin.berita.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'judul' => 'required|string|max:255',
            'isi_berita' => 'required',
            'tanggal_publikasi' => 'nullable|date',
            'foto' => 'nullable|image|max:2048'
        ]);

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('berita','public');
            $data['foto'] = $path;
        }

        Berita::create($data);
        return redirect()->route('admin.berita.index')->with('success','Berita berhasil ditambahkan');
    }

    public function edit(Berita $berita)
    {
        return view('admin.berita.edit', compact('berita'));
    }

    public function update(Request $request, Berita $berita)
    {
        $data = $request->validate([
            'judul' => 'required|string|max:255',
            'isi_berita' => 'required',
            'tanggal_publikasi' => 'nullable|date',
            'foto' => 'nullable|image|max:2048'
        ]);

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('berita','public');
            $data['foto'] = $path;
        }

        $berita->update($data);
        return redirect()->route('admin.berita.index')->with('success','Berita berhasil diperbarui');
    }

    public function destroy(Berita $berita)
    {
        $berita->delete();
        return redirect()->route('admin.berita.index')->with('success','Berita berhasil dihapus');
    }
}
