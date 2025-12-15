<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakulikuler;
use Illuminate\Http\Request;

class EkstrakulikulerApiController extends Controller
{
    public function index()
    {
        return response()->json(Ekstrakulikuler::with('pembina')->orderBy('nama_ekskul')->get());
    }

    public function show($id)
    {
        $ekskul = Ekstrakulikuler::with('pembina')->findOrFail($id);
        return response()->json($ekskul);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_ekskul' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'hari' => 'nullable|string|max:50',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after_or_equal:jam_mulai',
            'pembina_id' => 'nullable|integer|exists:guru_staf,id',
            'foto' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $ekskul = Ekstrakulikuler::create($validated);
        return response()->json($ekskul, 201);
    }

    public function update(Request $request, $id)
    {
        $ekskul = Ekstrakulikuler::findOrFail($id);

        $validated = $request->validate([
            'nama_ekskul' => 'sometimes|required|string|max:100',
            'deskripsi' => 'nullable|string',
            'hari' => 'nullable|string|max:50',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after_or_equal:jam_mulai',
            'pembina_id' => 'nullable|integer|exists:guru_staf,id',
            'foto' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $ekskul->update($validated);
        return response()->json($ekskul);
    }

    public function destroy($id)
    {
        $ekskul = Ekstrakulikuler::findOrFail($id);
        $ekskul->delete();
        return response()->json(['message' => 'Ekstrakurikuler dihapus']);
    }
}
