<?php

namespace App\Http\Controllers\Humas;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Http\Resources\BeritaResource;
use App\Http\Requests\StoreBeritaRequest;
use App\Http\Requests\UpdateBeritaRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class BeritaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 10), 100);
            $berita  = Berita::orderByDesc('tanggal_publikasi')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => BeritaResource::collection($berita),
                'meta'    => [
                    'current_page' => $berita->currentPage(),
                    'last_page'    => $berita->lastPage(),
                    'per_page'     => $berita->perPage(),
                    'total'        => $berita->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Humas: Failed to fetch berita list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar berita',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Berita $berita): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new BeritaResource($berita),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Humas: Failed to fetch berita detail', [
                'berita_id' => (string) $berita->id,
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail berita',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreBeritaRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            if ($request->hasFile('foto')) {
                $data['foto'] = $request->file('foto')->store('uploads/berita', 'public');
            }

            $berita = Berita::create($data)->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil ditambahkan oleh Humas.',
                'data'    => new BeritaResource($berita),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            if (!empty($data['foto'] ?? null)) {
                Storage::disk('public')->delete($data['foto']);
            }
            Log::error('Humas: Failed to create berita', ['payload' => $data, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan berita',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateBeritaRequest $request, Berita $berita): JsonResponse
    {
        $data = $request->validated();

        if (! $berita->exists) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            if ($request->hasFile('foto')) {
                if ($berita->foto) {
                    Storage::disk('public')->delete($berita->foto);
                }
                $data['foto'] = $request->file('foto')->store('uploads/berita', 'public');
            }

            unset($data['id']);

            foreach ($data as $key => $value) {
                if ($value === null || $value === '') {
                    unset($data[$key]);
                }
            }

            $berita->fill($data)->save();
            $berita->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil diperbarui oleh Humas.',
                'data'    => new BeritaResource($berita),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            if (!empty($data['foto'] ?? null)) {
                Storage::disk('public')->delete($data['foto']);
            }
            Log::error('Humas: Failed to update berita', [
                'berita_id' => (string) $berita->id,
                'payload'   => $data,
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui berita',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Berita $berita): JsonResponse
    {
        try {
            if ($berita->foto) {
                Storage::disk('public')->delete($berita->foto);
            }

            $berita->delete();

            return response()->json([
                'success'      => true,
                'message'      => 'Berita berhasil dihapus oleh Humas',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Humas: Failed to delete berita', [
                'berita_id' => (string) $berita->id,
                'error'     => $e->getMessage()
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus berita',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}