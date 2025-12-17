<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use App\Http\Resources\KurikulumResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KurikulumController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:Admin');
    }

    public function index()
    {
        $data = Kurikulum::paginate(12);
        
        return KurikulumResource::collection($data);
    }
    
    public function show($id)
    {
        $item = Kurikulum::findOrFail($id);
        
        return new KurikulumResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'penjelasan_kurikulum' => 'nullable|string',
            'file_jadwal' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        $filePath = null;
        if ($request->hasFile('file_jadwal')) {
            $filePath = $request->file('file_jadwal')->store('uploads/kurikulum','public');
        }

        $kurikulum = Kurikulum::create([
            'judul' => $validated['judul'],
            'penjelasan_kurikulum' => $validated['penjelasan_kurikulum'] ?? null,
            'file_jadwal_path' => $filePath, // Sesuaikan kolom di database jika berbeda
        ]);
        
        return new KurikulumResource($kurikulum);
    }

    public function update(Request $request, $id)
    {
        $item = Kurikulum::findOrFail($id);

        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'penjelasan_kurikulum' => 'nullable|string',
            'file_jadwal' => 'nullable|file|mimes:pdf,doc,docx|max:5120',
        ]);

        if ($request->hasFile('file_jadwal')) {
            if ($item->file_jadwal_path) {
                Storage::disk('public')->delete($item->file_jadwal_path);
            }
            $item->file_jadwal_path = $request->file('file_jadwal')->store('uploads/kurikulum','public');
        }

        $item->judul = $validated['judul'];
        // Menggunakan array update() agar lebih bersih, pastikan kolom yang tidak termasuk file juga masuk ke $validated
        $item->penjelasan_kurikulum = $validated['penjelasan_kurikulum'] ?? $item->penjelasan_kurikulum; 
        
        $item->save();
        
        return new KurikulumResource($item);
    }

    public function destroy($id)
    {
        $item = Kurikulum::findOrFail($id);

        if ($item->file_jadwal_path) {
            Storage::disk('public')->delete($item->file_jadwal_path);
        }

        $item->delete();
        
        return response()->json(null, 204);
    }
}