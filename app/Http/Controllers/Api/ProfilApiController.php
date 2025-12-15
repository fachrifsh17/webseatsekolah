<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfilSekolah;
use Illuminate\Http\Request;

class ProfilApiController extends Controller
{
    public function index()
    {
        return response()->json(ProfilSekolah::all());
    }

    public function show($id)
    {
        $profil = ProfilSekolah::findOrFail($id);
        return response()->json($profil);
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
        return response()->json($profil, 201);
    }

    public function update(Request $request, $id)
    {
        $profil = ProfilSekolah::findOrFail($id);

        $validated = $request->validate([
            'sejarah' => 'nullable|string',
            'visi' => 'nullable|string',
            'misi' => 'nullable|string',
            'npsn' => 'nullable|string|max:20',
            'akreditasi' => 'nullable|string|max:10',
            'sambutan_kepsek' => 'nullable|string',
        ]);

        $profil->update($validated);
        return response()->json($profil);
    }

    public function destroy($id)
    {
        $profil = ProfilSekolah::findOrFail($id);
        $profil->delete();
        return response()->json(['message' => 'Profil sekolah dihapus']);
    }
}
