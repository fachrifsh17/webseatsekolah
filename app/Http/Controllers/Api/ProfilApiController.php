<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfilApiController extends Controller
{
    public function index()
    {
        $data = ProfilSekolah::all();
        // Mengembalikan data, status default 200 OK
        return response()->json($data, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(ProfilSekolah $profil)
    {
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json($profil, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sejarah' => 'nullable|string',
            'visi' => 'nullable|string',
            'misi' => 'nullable|string',
            'npsn' => 'nullable|string|max:20',
            'akreditasi' => 'nullable|string|max:10',
            'sambutan_kepsek' => 'nullable|string',
        ]);

        $profil = ProfilSekolah::create($validated);
        return response()->json($profil, Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, ProfilSekolah $profil)
    {
        $validated = $request->validate([
            'sejarah' => 'nullable|string',
            'visi' => 'nullable|string',
            'misi' => 'nullable|string',
            'npsn' => 'nullable|string|max:20',
            'akreditasi' => 'nullable|string|max:10',
            'sambutan_kepsek' => 'nullable|string',
        ]);

        $profil->update($validated);
        return response()->json($profil, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(ProfilSekolah $profil)
    {
        $profil->delete();
        return response()->json(['message' => 'Profil sekolah berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}