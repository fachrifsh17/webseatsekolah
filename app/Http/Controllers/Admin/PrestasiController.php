<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use App\Http\Resources\PrestasiResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrestasiController extends Controller
{
    public function __construct()
    {
        // Menggunakan auth:sanctum dan role:Admin untuk konsistensi API
        $this->middleware('auth:sanctum'); 
        $this->middleware('role:Admin');
    }

    public function index()
    {
        $items = Prestasi::orderBy('tahun','desc')->paginate(12);
        
        return PrestasiResource::collection($items);
    }
    
    public function show($id)
    {
        $item = Prestasi::findOrFail($id);
        
        return new PrestasiResource($item);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'tahun' => 'nullable|digits:4|integer',
            'tingkat' => 'nullable|string|max:50',
            'kategori' => 'nullable|in:Siswa,Sekolah',
            'foto' => 'nullable|image|max:2048'
        ]);
        
        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('uploads/prestasi','public');
        } else {
            unset($validated['foto']);
        }
        
        $prestasi = Prestasi::create($validated);
        
        return new PrestasiResource($prestasi);
    }

    // Menggunakan Route Model Binding
    public function update(Request $request, Prestasi $prestasi)
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'tahun' => 'nullable|digits:4|integer',
            'tingkat' => 'nullable|string|max:50',
            'kategori' => 'nullable|in:Siswa,Sekolah',
            // File foto bersifat opsional untuk update
            'foto' => 'nullable|image|max:2048' 
        ]);
        
        if ($request->hasFile('foto')) {
            if ($prestasi->foto) {
                Storage::disk('public')->delete($prestasi->foto);
            }
            $validated['foto'] = $request->file('foto')->store('uploads/prestasi','public');
        } else {
            unset($validated['foto']);
        }
        
        $prestasi->update($validated);
        
        return new PrestasiResource($prestasi);
    }

    public function destroy(Prestasi $prestasi)
    {
        if ($prestasi->foto) {
            Storage::disk('public')->delete($prestasi->foto);
        }
        
        $prestasi->delete();
        
        return response()->json(null, 204);
    }
}