<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fasilitas;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FasilitasApiController extends Controller
{
    public function index()
    {
        $data = Fasilitas::orderBy('id', 'desc')->get();
        return response()->json(['success' => true, 'data' => $data], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function show(Fasilitas $fasilita) // Nama variabel singular default Laravel adalah $fasilita
    {
        // Jika tidak ditemukan, Laravel akan otomatis melempar 404
        return response()->json(['success' => true, 'data' => $fasilita], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_fasilitas' => 'required|string|max:150',
            // Asumsi 'foto' adalah path/URL, bukan file upload
            'foto' => 'nullable|string|max:255', 
            'keterangan' => 'nullable|string|max:255',
        ]);
        
        $f = Fasilitas::create($validated);
        return response()->json(['success' => true, 'data' => $f], Response::HTTP_CREATED);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, Fasilitas $fasilita)
    {
        $validated = $request->validate([
            'nama_fasilitas' => 'sometimes|required|string|max:150',
            'foto' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string|max:255',
        ]);
        
        $fasilita->update($validated);
        return response()->json(['success' => true, 'data' => $fasilita], Response::HTTP_OK);
    }

    // Menggunakan Route Model Binding
    public function destroy(Fasilitas $fasilita)
    {
        $fasilita->delete();
        return response()->json(['success' => true, 'message' => 'Fasilitas berhasil dihapus.'], Response::HTTP_NO_CONTENT);
    }
}