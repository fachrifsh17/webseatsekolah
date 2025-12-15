<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    public function index()
    {
        $data = Pengumuman::latest()->paginate(10);
        return view('admin.pengumuman.index', compact('data'));
    }

    public function create()
    {
        return view('admin.pengumuman.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'isi_pengumuman' => 'required|string',
            'tanggal_publikasi' => 'nullable|date',
            'penting' => 'nullable|boolean',
        ]);

        Pengumuman::create($request->only([
            'judul', 'isi_pengumuman', 'tanggal_publikasi', 'penting'
        ]));

        return redirect()->route('pengumuman.index')->with('ok', 'Pengumuman berhasil ditambahkan');
    }

    public function edit($id)
    {
        $item = Pengumuman::findOrFail($id);
        return view('admin.pengumuman.edit', compact('item'));
    }

    public function update(Request $request, $id)
    {
        $item = Pengumuman::findOrFail($id);

        $request->validate([
            'judul' => 'required|string|max:255',
            'isi_pengumuman' => 'required|string',
            'tanggal_publikasi' => 'nullable|date',
            'penting' => 'nullable|boolean',
        ]);

        $item->update($request->only([
            'judul', 'isi_pengumuman', 'tanggal_publikasi', 'penting'
        ]));

        return redirect()->route('pengumuman.index')->with('ok', 'Pengumuman berhasil diubah');
    }

    public function destroy($id)
    {
        Pengumuman::findOrFail($id)->delete();
        return redirect()->route('pengumuman.index')->with('ok', 'Pengumuman berhasil dihapus');
    }
}
