<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KurikulumResource;
use App\Models\Kurikulum; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class KurikulumApiController extends Controller
{
    public function index()
    {
        $kurikulum = Kurikulum::latest()->paginate(10); 
        return KurikulumResource::collection($kurikulum);
    }

    public function store(Request $request) // Ganti dengan KurikulumRequest $request jika sudah dibuat
    {
        // Contoh sederhana (perlu penanganan file upload dan validasi lengkap)
        $request->validate([
            'judul' => 'required|string|max:255',
            'penjelasan_kurikulum' => 'nullable|string',
            'file_jadwal' => 'nullable|file|mimes:pdf,doc,docx|max:5120', // Max 5MB
        ]);

        $data = $request->except(['file_jadwal']);
        
        if ($request->hasFile('file_jadwal')) {
            $path = $request->file('file_jadwal')->store('kurikulum', 'public');
            $data['file_jadwal_path'] = $path; // Simpan path ke kolom DB
        }

        $kurikulum = Kurikulum::create($data);

        return response()->json([
            'message' => 'Data kurikulum berhasil ditambahkan.',
            'data' => new KurikulumResource($kurikulum)
        ], 201);
    }

    public function show(Kurikulum $kurikulum)
    {
        return new KurikulumResource($kurikulum);
    }
    public function update(Request $request, Kurikulum $kurikulum) // Ganti dengan KurikulumRequest $request jika sudah dibuat
    {
        $request->validate([
            'judul' => 'required|string|max:255',
            'penjelasan_kurikulum' => 'nullable|string',
            'file_jadwal' => 'nullable|file|mimes:pdf,doc,docx|max:5120', 
        ]);
        
        $data = $request->except(['file_jadwal']);

        if ($request->hasFile('file_jadwal')) {
            // Hapus file lama jika ada
            if ($kurikulum->file_jadwal_path) {
                Storage::disk('public')->delete($kurikulum->file_jadwal_path);
            }
            $path = $request->file('file_jadwal')->store('kurikulum', 'public');
            $data['file_jadwal_path'] = $path;
        }

        $kurikulum->update($data);

        return response()->json([
            'message' => 'Data kurikulum berhasil diperbarui.',
            'data' => new KurikulumResource($kurikulum)
        ]);
    }

    public function destroy(Kurikulum $kurikulum)
    {
        if ($kurikulum->file_jadwal_path) {
            Storage::disk('public')->delete($kurikulum->file_jadwal_path);
        }

        $kurikulum->delete();
        
        return response()->json(null, 204);
    }
}