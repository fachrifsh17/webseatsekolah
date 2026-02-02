<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Jabatan;
use App\Http\Resources\JabatanResource;
use App\Http\Requests\StoreJabatanRequest;
use App\Http\Requests\UpdateJabatanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class JabatanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        try {
            // Mengambil semua jabatan dengan hitungan berapa banyak guru yang menjabat
            $data = Jabatan::withCount('strukturJabatan')->get();

            return response()->json([
                'success' => true,
                'data'    => JabatanResource::collection($data),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch jabatans', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar jabatan.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreJabatanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $item = DB::transaction(fn () => Jabatan::create($validated));

            return response()->json([
                'success' => true,
                'message' => 'Jabatan baru berhasil ditambahkan.',
                'data'    => new JabatanResource($item),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create jabatan', ['payload' => $validated, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jabatan.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Jabatan $jabatan): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new JabatanResource($jabatan->loadCount('strukturJabatan')),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch jabatan detail', ['id' => $jabatan->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail jabatan.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateJabatanRequest $request, Jabatan $jabatan): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::transaction(fn () => $jabatan->update($validated));

            return response()->json([
                'success' => true,
                'message' => 'Data jabatan berhasil diperbarui.',
                'data'    => new JabatanResource($jabatan),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update jabatan', ['id' => $jabatan->id, 'payload' => $validated, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data jabatan.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Jabatan $jabatan): JsonResponse
    {
        try {
            // Proteksi: Jangan hapus jika masih ada guru yang menggunakan jabatan ini
            if ($jabatan->strukturJabatan()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jabatan tidak bisa dihapus karena masih digunakan oleh beberapa guru.',
                ], Response::HTTP_CONFLICT);
            }

            DB::transaction(fn () => $jabatan->delete());

            return response()->json([
                'success'      => true,
                'message'      => 'Jabatan berhasil dihapus.',
                'notification' => 'Berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete jabatan', ['id' => $jabatan->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jabatan.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}