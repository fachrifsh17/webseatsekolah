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
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class StrukturJabatanController extends Controller
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
            $data = StrukturJabatan::with(['guru', 'jabatan'])->orderBy('urutan_tampil')->get();

            return response()->json([
                'success' => true,
                'data'    => StrukturJabatanResource::collection($data),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch struktur jabatan', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar struktur jabatan.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(StrukturJabatan $strukturJabatan): JsonResponse
    {
        try {
            $strukturJabatan->load(['guru', 'jabatan']);

            return response()->json([
                'success' => true,
                'data'    => new StrukturJabatanResource($strukturJabatan),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch struktur jabatan', ['id' => (string)$strukturJabatan->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail struktur jabatan.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreStrukturJabatanRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $guruExists = StrukturJabatan::where('guru_staf_id', $validated['guru_staf_id'])->exists();
        if ($guruExists) {
            return response()->json([
                'success' => false,
                'message' => 'Guru/Staf sudah memiliki struktur jabatan.',
                'errors'  => ['guru_staf_id' => ['Guru/Staf sudah memiliki struktur jabatan.']],
            ], Response::HTTP_CONFLICT);
        }

        $jabatanExists = StrukturJabatan::where('jabatan_id', $validated['jabatan_id'])->exists();
        if ($jabatanExists) {
            return response()->json([
                'success' => false,
                'message' => 'Jabatan sudah terisi oleh orang lain.',
                'errors'  => ['jabatan_id' => ['Jabatan ini sudah memiliki pejabat.']],
            ], Response::HTTP_CONFLICT);
        }

        try {
            $item = DB::transaction(fn () => StrukturJabatan::create($validated));
            $item->load(['guru', 'jabatan']);

            return response()->json([
                'success' => true,
                'message' => 'Data struktur jabatan berhasil ditambahkan.',
                'data'    => new StrukturJabatanResource($item),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create struktur jabatan', ['payload' => $validated, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan struktur jabatan.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateStrukturJabatanRequest $request, StrukturJabatan $strukturJabatan): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['guru_staf_id'])) {
            $guruConflict = StrukturJabatan::where('guru_staf_id', $validated['guru_staf_id'])
                ->where('id', '<>', $strukturJabatan->id)
                ->exists();

            if ($guruConflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guru/Staf sudah memiliki struktur jabatan.',
                    'errors'  => ['guru_staf_id' => ['Guru/Staf sudah memiliki struktur jabatan.']],
                ], Response::HTTP_CONFLICT);
            }
        }

        if (isset($validated['jabatan_id'])) {
            $jabatanConflict = StrukturJabatan::where('jabatan_id', $validated['jabatan_id'])
                ->where('id', '<>', $strukturJabatan->id)
                ->exists();

            if ($jabatanConflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jabatan sudah terisi oleh orang lain.',
                    'errors'  => ['jabatan_id' => ['Jabatan ini sudah memiliki pejabat.']],
                ], Response::HTTP_CONFLICT);
            }
        }

        try {
            DB::transaction(fn () => $strukturJabatan->update($validated));
            $strukturJabatan->refresh();
            $strukturJabatan->load(['guru', 'jabatan']);

            return response()->json([
                'success' => true,
                'message' => 'Data struktur jabatan berhasil diperbarui.',
                'data'    => new StrukturJabatanResource($strukturJabatan),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update struktur jabatan', ['id' => (string)$strukturJabatan->id, 'payload' => $validated, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui struktur jabatan.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(StrukturJabatan $strukturJabatan): JsonResponse
    {
        try {
            DB::transaction(fn () => $strukturJabatan->delete());

            return response()->json([
                'success'      => true,
                'message'      => 'Data struktur jabatan berhasil dihapus',
                'notification' => 'Berhasil dihapus',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete struktur jabatan', ['id' => (string)$strukturJabatan->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus struktur jabatan.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}