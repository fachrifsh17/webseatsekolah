<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KalenderApiController extends Controller
{
    public function index()
    {
        $data = KalenderAkademik::orderBy('tanggal_mulai')->get();
        return response()->json($data, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(KalenderAkademik $kalender)
    {
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json($kalender, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kegiatan' => 'required|string|max:255',
            'tanggal_mulai' => 'nullable|date',
            // Memastikan tanggal selesai >= tanggal mulai
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'kategori' => 'nullable|string|max:50',
        ]);

        $k = KalenderAkademik::create($validated);
        return response()->json($k, Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, KalenderAkademik $kalender)
    {
        $validated = $request->validate([
            'kegiatan' => 'sometimes|required|string|max:255',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'kategori' => 'nullable|string|max:50',
        ]);

        $kalender->update($validated);
        return response()->json($kalender, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(KalenderAkademik $kalender)
    {
        $kalender->delete();
        return response()->json(['message' => 'Kegiatan kalender akademik berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}