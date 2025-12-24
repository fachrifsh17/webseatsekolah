<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Http\Resources\BeritaResource;
use App\Http\Requests\StoreBeritaRequest;
use App\Http\Requests\UpdateBeritaRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Throwable;

class BeritaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $berita = Berita::with('kategori')->orderByDesc('tanggal_publikasi')->paginate(10);
        return new JsonResponse(BeritaResource::collection($berita));
    }
    
    public function show(Berita $berita): JsonResponse
    {
        $berita->load('kategori');
        return new JsonResponse(new BeritaResource($berita));
    }

    public function store(StoreBeritaRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            if ($request->hasFile('foto')) {
                $data['foto'] = $request->file('foto')->store('uploads/berita', 'public');
            }

            $berita = Berita::create($data);
            
            return new JsonResponse(new BeritaResource($berita->load('kategori')), 201);
        } catch (Throwable $e) {
            if (!empty($data['foto'] ?? null)) {
                Storage::disk('public')->delete($data['foto']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menambahkan berita'
            ], 500);
        }
    }

    public function update(UpdateBeritaRequest $request, Berita $berita): JsonResponse
    {
        $data = $request->validated();

        try {
            if ($request->hasFile('foto')) {
                if ($berita->foto) {
                    Storage::disk('public')->delete($berita->foto);
                }
                $data['foto'] = $request->file('foto')->store('uploads/berita', 'public');
            }

            $berita->update($data);
            
            return new JsonResponse(new BeritaResource($berita->load('kategori')));
        } catch (Throwable $e) {
            if (!empty($data['foto'] ?? null)) {
                Storage::disk('public')->delete($data['foto']);
            }
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal memperbarui berita'
            ], 500);
        }
    }

    public function destroy(Berita $berita): JsonResponse
    {
        try {
            if ($berita->foto) {
                Storage::disk('public')->delete($berita->foto);
            }
            
            $berita->delete();
            
            return new JsonResponse(null, 204);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menghapus berita'
            ], 500);
        }
    }
}