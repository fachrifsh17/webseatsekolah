<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Http\Resources\AlbumResource; // <-- Wajib: Import Resource
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AlbumController extends Controller
{
    public function index()
    {
        $data = Album::orderByDesc('tanggal_kegiatan')->paginate(12);
        
        // Ganti return view() dengan Resource Collection
        return AlbumResource::collection($data);
    }

    public function show($id)
    {
        $item = Album::findOrFail($id);
        
        // Kembalikan Single Resource
        return new AlbumResource($item);
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

        $album = Album::create([
            'nama_album' => $validated['nama_album'],
            'tanggal_kegiatan' => $validated['tanggal_kegiatan'] ?? null,
            'cover_path' => $coverPath,
        ]);

        // Kembalikan Resource yang baru dibuat (HTTP 201 Created)
        return new AlbumResource($album);
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

        // Kembalikan Resource yang telah diperbarui
        return new AlbumResource($item);
    }

    public function destroy($id)
    {
        $item = Album::findOrFail($id);

        if ($item->cover_path) {
            Storage::disk('public')->delete($item->cover_path);
        }

        $item->delete();

        // Kembalikan respons 204 No Content
        return response()->json(null, 204);
    }
}     