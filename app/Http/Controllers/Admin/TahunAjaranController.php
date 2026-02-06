<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use App\Models\Kurikulum;
use App\Http\Requests\StoreTahunAjaranRequest;
use App\Http\Requests\UpdateTahunAjaranRequest;
use App\Http\Resources\TahunAjaranResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class TahunAjaranController extends Controller
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
            $tahunAjaran = TahunAjaran::with('kurikulum')->orderBy('nama', 'desc')->get();

            return response()->json([
                'success' => true,
                'data'    => TahunAjaranResource::collection($tahunAjaran),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch tahun ajaran', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar tahun ajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreTahunAjaranRequest $request): JsonResponse
    {
        try {
            $exists = TahunAjaran::where('nama', $request->nama)
                ->where('semester', $request->semester)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran ' . $request->nama . ' semester ' . $request->semester . ' sudah terdaftar.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tahunAjaran = DB::transaction(function () use ($request) {
                $data = $request->validated();

                if (empty($data['kurikulum_id'])) {
                    $activeKurikulum = Kurikulum::where('is_active', true)->first();
                    $data['kurikulum_id'] = $activeKurikulum?->id;
                }

                if (!empty($data['is_active']) && $data['is_active'] == true) {
                    TahunAjaran::where('is_active', true)->update(['is_active' => false]);
                }

                return TahunAjaran::create($data);
            });

            return response()->json([
                'success' => true,
                'message' => 'Tahun ajaran berhasil ditambahkan.',
                'data'    => new TahunAjaranResource($tahunAjaran->load('kurikulum'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create tahun ajaran', ['payload' => $request->validated(), 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan tahun ajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(TahunAjaran $tahunAjaran): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new TahunAjaranResource($tahunAjaran->load('kurikulum')),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch tahun ajaran detail', ['tahun_ajaran_id' => (string) $tahunAjaran->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail tahun ajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateTahunAjaranRequest $request, TahunAjaran $tahunAjaran): JsonResponse
    {
        try {
            $exists = TahunAjaran::where('nama', $request->nama)
                ->where('semester', $request->semester)
                ->where('id', '!=', $tahunAjaran->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran ' . $request->nama . ' semester ' . $request->semester . ' sudah digunakan data lain.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::transaction(function () use ($request, $tahunAjaran) {
                $data = $request->validated();

                // Logika otomatis kurikulum jika tidak diinput
                if (empty($data['kurikulum_id'])) {
                    $activeKurikulum = Kurikulum::where('is_active', true)->first();
                    $data['kurikulum_id'] = $activeKurikulum?->id;
                }

                if (!empty($data['is_active']) && $data['is_active'] == true) {
                    TahunAjaran::where('id', '!=', (string) $tahunAjaran->id)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
                }
                
                $tahunAjaran->update($data);
            });

            return response()->json([
                'success' => true,
                'message' => 'Tahun ajaran berhasil diperbarui.',
                'data'    => new TahunAjaranResource($tahunAjaran->refresh()->load('kurikulum'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update tahun ajaran', ['tahun_ajaran_id' => (string) $tahunAjaran->id, 'payload' => $request->validated(), 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui tahun ajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(TahunAjaran $tahunAjaran): JsonResponse
    {
        if ($tahunAjaran->kelas()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus tahun ajaran yang masih memiliki data kelas.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::transaction(fn() => $tahunAjaran->delete());

            return response()->json([
                'success' => true,
                'message' => 'Tahun ajaran berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete tahun ajaran', ['tahun_ajaran_id' => (string) $tahunAjaran->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus tahun ajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}