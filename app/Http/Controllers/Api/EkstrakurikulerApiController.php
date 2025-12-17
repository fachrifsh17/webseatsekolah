<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ekstrakurikuler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EkstrakurikulerApiController extends Controller
{
    public function index()
    {
        $ekskul = Ekstrakurikuler::with('pembina')->orderBy('nama_ekskul')->get();
        // Mengembalikan data langsung, status default 200 OK
        return response()->json($ekskul, Response::HTTP_OK); 
    }

    // Menggunakan Route Model Binding
    public function show(Ekstrakurikuler $ekskul)
    {
        $ekskul->load('pembina');
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404 (dikelola oleh Exception Handler)
        return response()->json($ekskul, Response::HTTP_OK);
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

        $ekskul = Ekstrakurikuler::create($validated);
        return response()->json($ekskul, Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, Ekstrakurikuler $ekskul)
    {
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
        return response()->json($ekskul, Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(Ekstrakurikuler $ekskul)
    {
        $ekskul->delete();
        return response()->json(['message' => 'Ekstrakurikuler berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}