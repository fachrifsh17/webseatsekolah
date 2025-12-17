<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BannerApiController extends Controller
{
    public function index()
    {
        // Mengambil banner yang aktif (aktif_sampai NULL ATAU aktif_sampai >= sekarang)
        $banners = Banner::whereNull('aktif_sampai')
            ->orWhere('aktif_sampai', '>=', now())
            ->orderBy('id', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $banners], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'url_link' => 'nullable|url|max:255',
            'aktif_sampai' => 'nullable|date',
            // Asumsi 'foto' adalah path/URL, bukan file upload
            'foto' => 'nullable|string|max:255', 
        ]);

        $banner = Banner::create($validated);

        return response()->json(['success' => true, 'data' => $banner], Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'judul' => 'sometimes|required|string|max:255',
            'url_link' => 'nullable|url|max:255',
            'aktif_sampai' => 'nullable|date',
            'foto' => 'nullable|string|max:255',
        ]);
        
        $banner->update($validated);

        return response()->json(['success' => true, 'data' => $banner], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(Banner $banner)
    {
        $banner->delete();
        return response()->json(['success' => true, 'message' => 'Banner berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}