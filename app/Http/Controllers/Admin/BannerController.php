<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Http\Resources\BannerResource; // Wajib: Import Resource
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        $data = Banner::orderByDesc('aktif_sampai')->paginate(12);
        
        // Ganti return view() dengan Resource Collection
        return BannerResource::collection($data);
    }

    public function show($id)
    {
        $item = Banner::findOrFail($id);
        
        // Kembalikan Single Resource
        return new BannerResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'foto' => 'nullable|image|max:2048',
            'url_link' => 'nullable|url|max:255',
            'aktif_sampai' => 'nullable|date',
        ]);

        $fotoPath = $request->hasFile('foto')
            ? $request->file('foto')->store('uploads/banner', 'public')
            : null;

        $banner = Banner::create([
            'judul' => $validated['judul'],
            'url_link' => $request->url_link,
            'aktif_sampai' => $request->aktif_sampai,
            'foto' => $fotoPath,
        ]);

        // Kembalikan Resource yang baru dibuat (HTTP 201 Created)
        return new BannerResource($banner);
    }

    public function update(Request $request, $id)
    {
        $item = Banner::findOrFail($id);

        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'foto' => 'nullable|image|max:2048',
            'url_link' => 'nullable|url|max:255',
            'aktif_sampai' => 'nullable|date',
        ]);

        if ($request->hasFile('foto')) {
            if ($item->foto) {
                Storage::disk('public')->delete($item->foto);
            }
            $item->foto = $request->file('foto')->store('uploads/banner', 'public');
        }

        $item->judul = $validated['judul'];
        $item->url_link = $request->url_link;
        $item->aktif_sampai = $request->aktif_sampai;
        $item->save();

        // Kembalikan Resource yang telah diperbarui
        return new BannerResource($item);
    }

    public function destroy($id)
    {
        $item = Banner::findOrFail($id);

        if ($item->foto) {
            Storage::disk('public')->delete($item->foto);
        }

        $item->delete();
        
        // Kembalikan respons 204 No Content
        return response()->json(null, 204);
    }
}