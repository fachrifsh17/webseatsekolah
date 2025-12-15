<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AlbumController extends Controller
{
    public function index()
    {
        $data = Album::orderByDesc('tanggal_kegiatan')->paginate(12);
        return view('admin.album.index', compact('data'));
    }

    public function create()
    {
        return view('admin.album.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_album' => 'required|string|max:255',
            'tanggal_kegiatan' => 'nullable|date',
            'cover' => 'nullable|image|max:2048',
        ]);

        $coverPath = $request->hasFile('cover')
            ? $request->file('cover')->store('uploads/album', 'public')
            : null;

        Album::create([
            'nama_album' => $validated['nama_album'],
            'tanggal_kegiatan' => $validated['tanggal_kegiatan'] ?? null,
            'cover_path' => $coverPath,
        ]);

        return redirect()->route('admin.album.index')->with('ok', 'Album dibuat');
    }

    public function edit($id)
    {
        $item = Album::findOrFail($id);
        return view('admin.album.edit', compact('item'));
    }

    public function update(Request $request, $id)
    {
        $item = Album::findOrFail($id);

        $validated = $request->validate([
            'nama_album' => 'required|string|max:255',
            'tanggal_kegiatan' => 'nullable|date',
            'cover' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('cover')) {
            if ($item->cover_path) {
                Storage::disk('public')->delete($item->cover_path);
            }
            $item->cover_path = $request->file('cover')->store('uploads/album', 'public');
        }

        $item->nama_album = $validated['nama_album'];
        $item->tanggal_kegiatan = $validated['tanggal_kegiatan'] ?? null;
        $item->save();

        return redirect()->route('admin.album.index')->with('ok', 'Album diubah');
    }

    public function destroy($id)
    {
        $item = Album::findOrFail($id);

        if ($item->cover_path) {
            Storage::disk('public')->delete($item->cover_path);
        }

        $item->delete();

        return redirect()->route('admin.album.index')->with('ok', 'Album dihapus');
    }
}
