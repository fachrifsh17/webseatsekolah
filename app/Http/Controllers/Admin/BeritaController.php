<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Http\Resources\BeritaResource;
use App\Http\Requests\StoreBeritaRequest;
use App\Http\Requests\UpdateBeritaRequest;
use Illuminate\Support\Facades\Storage;

class BeritaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index()
    {
        $berita = Berita::with('kategori')->orderByDesc('tanggal_publikasi')->paginate(10);
        return BeritaResource::collection($berita);
    }
    
    public function show(Berita $berita)
    {
        $berita->load('kategori');
        return new BeritaResource($berita);
    }

    public function store(StoreBeritaRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('uploads/berita', 'public');
        }

        $berita = Berita::create($data);
        
        return new BeritaResource($berita->load('kategori'));
    }

    public function update(UpdateBeritaRequest $request, Berita $berita)
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            if ($berita->foto) {
                Storage::disk('public')->delete($berita->foto);
            }
            $data['foto'] = $request->file('foto')->store('uploads/berita', 'public');
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
        
        return response()->noContent();
    }
}