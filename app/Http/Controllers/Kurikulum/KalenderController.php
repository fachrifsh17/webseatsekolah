<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use App\Models\Semester;
use App\Http\Resources\KalenderAkademikResource;
use App\Http\Requests\StoreKalenderAkademikRequest;
use App\Http\Requests\UpdateKalenderAkademikRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class KalenderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
        $this->authorizeResource(KalenderAkademik::class, 'kalender');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->query('per_page', 12), 100);
            
            $semesterId = request()->query('semester_id');
            if (!$semesterId) {
                $semesterId = Semester::where('is_active', true)->value('id');
            }

            $query = KalenderAkademik::query();

            if ($semesterId) {
                $query->where('semester_id', $semesterId);
            }

            $data = $query->orderBy('tanggal_mulai', 'desc')->paginate($perPage);
            $paginationData = $data->toArray();

            return response()->json([
                'success' => true,
                'data'    => KalenderAkademikResource::collection($data),
                'meta'    => [
                    'current_page'    => $data->currentPage(),
                    'last_page'       => $data->lastPage(),
                    'per_page'        => $data->perPage(),
                    'total'           => $data->total(),
                    'from'            => $data->firstItem(),
                    'to'              => $data->lastItem(),
                    'next_page_url'   => $data->nextPageUrl(),
                    'prev_page_url'   => $data->previousPageUrl(),
                    'path'            => $paginationData['path'],
                    'links'           => $paginationData['links'],
                    'semester_id'     => $semesterId,
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
            $kalender->load('semester');
            return response()->json([
                'success' => true,
                'data'    => new KalenderAkademikResource($kalender),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch kalender akademik detail', ['error' => $e->getMessage()]);
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

        if (empty($validated['semester_id'])) {
            $validated['semester_id'] = Semester::where('is_active', true)->value('id');
            
            if (!$validated['semester_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal: Tidak ada Semester yang aktif.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $isDuplicate = KalenderAkademik::where('kegiatan', $validated['kegiatan'])
            ->where('tanggal_mulai', $validated['tanggal_mulai'])
            ->where('semester_id', $validated['semester_id'])
            ->exists();

        if ($isDuplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Kegiatan dengan nama dan tanggal mulai yang sama sudah ada di semester ini.',
                'errors'  => ['conflict' => ['Data duplikat terdeteksi.']]
            ], Response::HTTP_CONFLICT);
        }

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
            Log::error('Failed to create kalender akademik', ['error' => $e->getMessage()]);
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

        $semesterId = $validated['semester_id'] ?? $kalender->semester_id;
        if (empty($semesterId)) {
            $semesterId = Semester::where('is_active', true)->value('id');
        }
        $validated['semester_id'] = $semesterId;

        $kegiatan = $validated['kegiatan'] ?? $kalender->kegiatan;
        $tanggalMulai = $validated['tanggal_mulai'] ?? $kalender->tanggal_mulai;

        $isDuplicate = KalenderAkademik::where('id', '!=', $kalender->id)
            ->where('kegiatan', $kegiatan)
            ->where('tanggal_mulai', $tanggalMulai)
            ->where('semester_id', $semesterId)
            ->exists();

        if ($isDuplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui: Data kegiatan serupa sudah terdaftar di semester ini.',
                'errors'  => ['conflict' => ['Data duplikat terdeteksi.']]
            ], Response::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
            $kalender->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kalender akademik berhasil diperbarui.',
                'data'    => new KalenderAkademikResource($kalender->fresh()),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update kalender akademik', ['error' => $e->getMessage()]);
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
                'success' => true,
                'message' => 'Kalender akademik berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete kalender akademik', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kalender akademik',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}