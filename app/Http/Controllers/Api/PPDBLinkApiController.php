<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PPDBLink;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PpdbLinkApiController extends Controller
{
    public function index()
    {
        $data = PPDBLink::all();
        // Mengembalikan data langsung, status default 200 OK
        return response()->json($data, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(PPDBLink $ppdbLink)
    {
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json($ppdbLink, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'url_link' => 'required|url|max:255',
            'status_ppdb' => 'nullable|in:Buka,Tutup,Segera',
        ]);

        $link = PPDBLink::create($validated);
        return response()->json($link, Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, PPDBLink $ppdbLink)
    {
        $validated = $request->validate([
            // 'url_link' tidak perlu 'sometimes' karena nullable, tapi 'required' dihapus untuk update
            'url_link' => 'nullable|url|max:255', 
            'status_ppdb' => 'nullable|in:Buka,Tutup,Segera',
        ]);

        $ppdbLink->update($validated);
        return response()->json($ppdbLink, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(PPDBLink $ppdbLink)
    {
        $ppdbLink->delete();
        return response()->json(['message' => 'PPDB link berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}