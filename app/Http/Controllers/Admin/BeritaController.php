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
use Illuminate\Support\Facades\Log;

class BeritaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        $berita = Berita::orderByDesc('tanggal_publikasi')->paginate(10);
        return new JsonResponse(BeritaResource::collection($berita));
    }

    public function show(Berita $berita): JsonResponse
    {
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
            $berita->refresh();

            return new JsonResponse(new BeritaResource($berita), 201);
        } catch (Throwable $e) {
            if (!empty($data['foto'] ?? null)) {
                Storage::disk('public')->delete($data['foto']);
            }
            Log::error('Berita store error: '.$e->getMessage(), ['exception' => $e]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menambahkan berita'
            ], 500);
        }
    }

    public function update(UpdateBeritaRequest $request, Berita $berita): JsonResponse
    {
        $data = $request->validated();

        if (! $berita->exists) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }

        try {
            if ($request->hasFile('foto')) {
                if ($berita->foto) {
                    Storage::disk('public')->delete($berita->foto);
                }
                $data['foto'] = $request->file('foto')->store('uploads/berita', 'public');
            }

            if (array_key_exists('id', $data)) {
                unset($data['id']);
            }

            foreach ($data as $key => $value) {
                if ($value === null || $value === '') {
                    unset($data[$key]);
                }
            }

            $berita->fill($data);
            $berita->save();
            $berita->refresh();

            return new JsonResponse(new BeritaResource($berita));
        } catch (Throwable $e) {
            if (!empty($data['foto'] ?? null)) {
                Storage::disk('public')->delete($data['foto']);
            }
            Log::error('Berita update error: '.$e->getMessage(), ['exception' => $e]);
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

            return new JsonResponse([
                'success'      => true,
                'message'      => 'Berita berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], 200);
        } catch (Throwable $e) {
            Log::error('Berita destroy error: '.$e->getMessage(), ['exception' => $e]);
            return new JsonResponse([
                'success' => false,
                'message' => 'Gagal menghapus berita'
            ], 500);
        }
    }
}
