<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KalenderAkademik;
use App\Models\TahunAjaran;
use App\Http\Resources\KalenderAkademikResource;
use App\Http\Requests\{StoreKalenderAkademikRequest, UpdateKalenderAkademikRequest};
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class KalenderController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);

        $this->authorizeResource(KalenderAkademik::class, 'kalender');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            
            // LOGIKA OTOMATIS: Cari ID Tahun Ajaran yang Aktif jika tidak ada filter
            $tahunAjaranId = request()->get('tahun_ajaran_id');
            if (!$tahunAjaranId) {
                $tahunAjaranId = TahunAjaran::where('is_active', true)->value('id');
            }

            $query = KalenderAkademik::query();

            if ($tahunAjaranId) {
                $query->where('tahun_ajaran_id', $tahunAjaranId);
            }

            $data = $query->orderBy('tanggal_mulai', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => KalenderAkademikResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kurikulum Kalender Index Error: ' . $e->getMessage());
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
            $kalender->load('tahunAjaran');
            return response()->json([
                'success' => true,
                'data'    => new KalenderAkademikResource($kalender),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Kurikulum Kalender Show Error: ' . $e->getMessage());
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

        // OTOMATIS: Cari tahun ajaran aktif jika input kosong
        if (empty($validated['tahun_ajaran_id'])) {
            $validated['tahun_ajaran_id'] = TahunAjaran::where('is_active', true)->value('id');
            
            if (!$validated['tahun_ajaran_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif di sistem.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Cek Duplikat
        $isDuplicate = KalenderAkademik::where('kegiatan', $validated['kegiatan'])
            ->where('tanggal_mulai', $validated['tanggal_mulai'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->exists();

        if ($isDuplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Kegiatan serupa sudah terdaftar pada tanggal tersebut di tahun ajaran ini.',
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
            Log::error('Kurikulum Kalender Store Error: ' . $e->getMessage());
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

        // OTOMATIS: Tetap gunakan tahun ajaran aktif jika tidak diubah
        $tahunAjaranId = $validated['tahun_ajaran_id'] ?? $kalender->tahun_ajaran_id;
        if (empty($tahunAjaranId)) {
            $tahunAjaranId = TahunAjaran::where('is_active', true)->value('id');
        }
        $validated['tahun_ajaran_id'] = $tahunAjaranId;

        $kegiatan = $validated['kegiatan'] ?? $kalender->kegiatan;
        $tanggalMulai = $validated['tanggal_mulai'] ?? $kalender->tanggal_mulai;

        // Cek Duplikat (Kecuali ID sendiri)
        $isDuplicate = KalenderAkademik::where('id', '!=', $kalender->id)
            ->where('kegiatan', $kegiatan)
            ->where('tanggal_mulai', $tanggalMulai)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->exists();

        if ($isDuplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Perubahan gagal: Nama kegiatan sudah ada di tanggal dan tahun ajaran tersebut.',
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
                'data'    => new KalenderAkademikResource($kalender),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Kurikulum Kalender Update Error: ' . $e->getMessage());
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
            Log::error('Kurikulum Kalender Delete Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kalender akademik',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}