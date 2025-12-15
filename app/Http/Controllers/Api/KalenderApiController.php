<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use Illuminate\Http\Request;

class KalenderApiController extends Controller
{
    public function index()
    {
        return response()->json(KalenderAkademik::orderBy('tanggal_mulai')->get());
    }

    public function show($id)
    {
        $k = KalenderAkademik::findOrFail($id);
        return response()->json($k);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kegiatan' => 'required|string|max:255',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'kategori' => 'nullable|string|max:50',
        ]);

        $k = KalenderAkademik::create($validated);
        return response()->json($k, 201);
    }

    public function update(Request $request, $id)
    {
        $k = KalenderAkademik::findOrFail($id);

        $validated = $request->validate([
            'kegiatan' => 'sometimes|required|string|max:255',
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'kategori' => 'nullable|string|max:50',
        ]);

        $k->update($validated);
        return response()->json($k);
    }

    public function destroy($id)
    {
        $k = KalenderAkademik::findOrFail($id);
        $k->delete();
        return response()->json(['message' => 'Kalender akademik dihapus']);
    }
}
