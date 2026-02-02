<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use App\Http\Resources\KalenderAkademikResource;
use App\Http\Requests\StoreKalenderAkademikRequest;
use App\Http\Requests\UpdateKalenderAkademikRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class KalenderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
        $this->middleware('log.admin')->only(['store', 'update', 'destroy']);
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $data    = KalenderAkademik::orderBy('tanggal_mulai')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => KalenderAkademikResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch kalender akademik list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar kalender akademik',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function show(KalenderAkademik $kalender): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new KalenderAkademikResource($kalender),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch kalender akademik detail', [
                'kalender_id' => (string) $kalender->id,
                'error'       => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail kalender akademik',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreKalenderAkademikRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $item = KalenderAkademik::create($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kalender akademik berhasil ditambahkan.',
                'data'    => new KalenderAkademikResource($item),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create kalender akademik', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan kalender akademik',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateKalenderAkademikRequest $request, KalenderAkademik $kalender): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $kalender->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kalender akademik berhasil diperbarui.',
                'data'    => new KalenderAkademikResource($kalender),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update kalender akademik', [
                'kalender_id' => (string) $kalender->id,
                'payload'     => $validated,
                'error'       => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui kalender akademik',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(KalenderAkademik $kalender): JsonResponse
    {
        DB::beginTransaction();
        try {
            $kalender->delete();
            DB::commit();

            return response()->json([
                'success'      => true,
                'message'      => 'Kalender akademik berhasil dihapus',
                'notification' => 'Berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete kalender akademik', [
                'kalender_id' => (string) $kalender->id,
                'error'       => $e->getMessage()
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Gagal menghapus kalender akademik',
                'notification' => 'Gagal dihapus',
                'errors'       => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
