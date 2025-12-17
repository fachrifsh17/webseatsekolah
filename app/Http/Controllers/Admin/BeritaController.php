<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Http\Resources\BeritaResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BeritaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        
        // Otorisasi: Sesuaikan dengan peran (Admin|Guru) yang diizinkan untuk mengelola Berita
        // $this->middleware('role:Admin|Guru'); 
    }

    public function index()
    {
        $berita = Berita::with('kategori')->orderBy('tanggal_publikasi','desc')->paginate(10);
        return BeritaResource::collection($berita);
    }
    
    public function show(Berita $berita)
    {
        $berita->load('kategori');
        return new BeritaResource($berita);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'judul' => 'required|string|max:255',
            'isi_berita' => 'required',
            'tanggal_publikasi' => 'required|date',
            'kategori_id' => 'required|exists:kategori,id',
            'foto' => 'nullable|image|max:2048'
        ]);

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('uploads/berita','public');
            $data['foto'] = $path;
        }

        $berita = Berita::create($data);
        
        return new BeritaResource($berita->load('kategori'));
    }

    public function update(Request $request, Berita $berita)
    {
        $data = $request->validate([
            'judul' => 'required|string|max:255',
            'isi_berita' => 'required',
            'tanggal_publikasi' => 'required|date',
            'kategori_id' => 'required|exists:kategori,id',
            'foto' => 'nullable|image|max:2048'
        ]);

        if ($request->hasFile('foto')) {
            if ($berita->foto) {
                Storage::disk('public')->delete($berita->foto);
            }
            $path = $request->file('foto')->store('uploads/berita','public');
            $data['foto'] = $path;
        }

        $berita->update($data);
        
        return new BeritaResource($berita->load('kategori'));
    }

    public function destroy(Berita $berita)
    {
        if ($berita->foto) {
            Storage::disk('public')->delete($berita->foto);
        }
        
        $berita->delete();
        
        return response()->json(null, 204);
    }
}