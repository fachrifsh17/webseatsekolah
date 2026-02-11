<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StrukturJabatan;
use App\Http\Resources\StrukturJabatanResource;
use App\Http\Requests\StoreStrukturJabatanRequest;
use App\Http\Requests\UpdateStrukturJabatanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class StrukturJabatanController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);

        $this->authorizeResource(StrukturJabatan::class, 'struktur_jabatan');
    }

    public function index(): JsonResponse
    {
        try {
            $data = StrukturJabatan::with(['guru', 'jabatan'])
                ->orderBy('urutan_tampil', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => StrukturJabatanResource::collection($data),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Struktur Jabatan Index Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar struktur jabatan.',
                'debug'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreStrukturJabatanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $conflict = StrukturJabatan::where('guru_staf_id', $validated['guru_staf_id'])
            ->orWhere('jabatan_id', $validated['jabatan_id'])
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Konflik Data: Guru sudah menjabat atau jabatan sudah terisi.',
                'errors'  => ['conflict' => ['Pastikan guru dan jabatan tidak ganda.']]
            ], Response::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
            $item = StrukturJabatan::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Struktur jabatan berhasil ditambahkan.',
                'data'    => new StrukturJabatanResource($item->load(['guru', 'jabatan'])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Store Struktur Jabatan Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateStrukturJabatanRequest $request, StrukturJabatan $strukturJabatan): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['guru_staf_id']) || isset($validated['jabatan_id'])) {
            $conflict = StrukturJabatan::where('id', '!=', $strukturJabatan->id)
                ->where(function($q) use ($validated) {
                    $q->where('guru_staf_id', $validated['guru_staf_id'] ?? null)
                      ->orWhere('jabatan_id', $validated['jabatan_id'] ?? null);
                })->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data baru bertabrakan dengan data jabatan lain yang sudah ada.',
                ], Response::HTTP_CONFLICT);
            }
        }

        DB::beginTransaction();
        try {
            $strukturJabatan->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Struktur jabatan berhasil diperbarui.',
                'data'    => new StrukturJabatanResource($strukturJabatan->load(['guru', 'jabatan'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(StrukturJabatan $strukturJabatan): JsonResponse
    {
        try {
            $strukturJabatan->delete();
            return response()->json([
                'success' => true,
                'message' => 'Data struktur jabatan berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}