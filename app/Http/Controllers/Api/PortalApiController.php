<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PortalSosmed;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalApiController extends Controller
{
    public function index()
    {
        $data = PortalSosmed::orderBy('nama_platform')->get();
        return response()->json($data, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(PortalSosmed $portal)
    {
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json($portal, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_platform' => 'required|string|max:100',
            'url_link' => 'nullable|url|max:255',
            'tipe' => 'nullable|in:Sosial Media,Portal Khusus',
        ]);

        $portal = PortalSosmed::create($validated);
        return response()->json($portal, Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, PortalSosmed $portal)
    {
        $validated = $request->validate([
            'nama_platform' => 'sometimes|required|string|max:100',
            'url_link' => 'nullable|url|max:255',
            'tipe' => 'nullable|in:Sosial Media,Portal Khusus',
        ]);

        $portal->update($validated);
        return response()->json($portal, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(PortalSosmed $portal)
    {
        $portal->delete();
        return response()->json(['message' => 'Portal/Media sosial berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}