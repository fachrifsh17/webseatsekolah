<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Http\Resources\BeritaResource;
use App\Http\Requests\StoreBeritaRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BeritaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
            new Middleware('log.admin', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index()
    {
        $berita = Berita::with('kategori')->orderBy('tanggal_publikasi', 'desc')->paginate(10);
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

    public function update(StoreBeritaRequest $request, Berita $berita)
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
        
        return response()->json(null, 204);
    }
}