<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Http\Requests\StorePresensiRequest;
use App\Http\Requests\UpdatePresensiRequest;
use App\Http\Resources\PresensiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    protected function isAdminUser($user): bool
    {
        if (!$user || !method_exists($user, 'hasRole')) return false;
        return $user->hasRole('admin') || $user->hasRole('Admin') || $user->hasRole('ADMIN');
    }

    protected function findKelasDiwalikanByGuruStaf($guruStaf)
    {
        $guruStafId = optional($guruStaf)->id;
        return $guruStafId ? Kelas::where('wali_kelas_id', (string) $guruStafId)->first() : null;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Presensi::class);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        $user = $request->user();
        $isAdmin = $this->isAdminUser($user);
        $kelasObj = $this->findKelasDiwalikanByGuruStaf($user->guruStaf);
        $kelasId = optional($kelasObj)->id;

        $query = Presensi::with(['siswa.kelas', 'tahunAjaran', 'guruStaf']);

        if (!$isAdmin) {
            if (!$kelasId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses kelas perwalian.'
                ], Response::HTTP_FORBIDDEN);
            }
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', (string) $kelasId));
        } elseif ($request->filled('kelas_id')) {
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', (string) $request->kelas_id));
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        }

        $perPage = min((int) $request->get('per_page', 50), 100);
        $data = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => PresensiResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Menampilkan daftar siswa di kelas perwalian
     */
    public function siswaWali(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Siswa::class);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        $user = $request->user();
        $isAdmin = $this->isAdminUser($user);

        if ($isAdmin && $request->filled('kelas_id')) {
            $kelasId = (string) $request->kelas_id;
        } else {
            $kelasId = optional($this->findKelasDiwalikanByGuruStaf($user->guruStaf))->id;
            if (!$kelasId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data kelas perwalian tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }
            $kelasId = (string) $kelasId;
        }

        $tanggal = $request->get('tanggal', date('Y-m-d'));

        $siswaQuery = Siswa::where('kelas_id', $kelasId)
            ->with(['kelas', 'presensi' => function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            }])
            ->when($request->filled('q'), fn($q) => $q->where('nama_lengkap', 'like', "%{$request->q}%"))
            ->orderBy('nama_lengkap', 'asc');

        $perPage = min((int) $request->get('per_page', 50), 100);
        $siswa = $siswaQuery->paginate($perPage);

        // Transform collection into PresensiResource per siswa (mengembalikan presensi hari itu atau placeholder)
        $collection = $siswa->getCollection()->map(function ($item) use ($tanggal) {
            $presensi = $item->presensi->first() ?? new Presensi([
                'siswa_id' => (string) $item->id,
                'tanggal'  => $tanggal,
                'status'   => null
            ]);
            $presensi->setRelation('siswa', $item);
            return new PresensiResource($presensi);
        })->values();

        return response()->json([
            'success' => true,
            'data'    => $collection,
            'meta'    => [
                'current_page' => $siswa->currentPage(),
                'last_page'    => $siswa->lastPage(),
                'per_page'     => $siswa->perPage(),
                'total'        => $siswa->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StorePresensiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Cast siswa_id to string to match varchar PKs
        $siswaId = (string) ($validated['siswa_id'] ?? null);
        $siswa = Siswa::findOrFail($siswaId);

        try {
            $this->authorize('createPresensiFor', $siswa);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        $tahunAjaran = TahunAjaran::where('is_active', true)->firstOrFail();

        $isRouteAdmin = RequestFacade::is('api/admin/*') || RequestFacade::is('admin/*');
        $guruStafId = $isRouteAdmin ? ($request->guru_staf_id ?? optional($user->guruStaf)->id) : optional($user->guruStaf)->id;
        $guruStafId = $guruStafId !== null ? (string) $guruStafId : null;

        try {
            $presensi = DB::transaction(function () use ($siswaId, $validated, $guruStafId, $tahunAjaran) {
                return Presensi::updateOrCreate(
                    ['siswa_id' => $siswaId, 'tanggal' => $validated['tanggal']],
                    [
                        'status'          => $validated['status'],
                        'keterangan'      => $validated['keterangan'] ?? null,
                        'guru_staf_id'    => $guruStafId,
                        'tahun_ajaran_id' => (string) $tahunAjaran->id,
                    ]
                );
            });

            $presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']);

            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil disimpan.',
                'data'    => new PresensiResource($presensi),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to store presensi', [
                'siswa_id' => $siswaId,
                'payload'  => $validated,
                'error'    => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan presensi.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdatePresensiRequest $request, Presensi $presensi): JsonResponse
    {
        try {
            $this->authorize('update', $presensi);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();

        if ((RequestFacade::is('api/admin/*') || RequestFacade::is('admin/*')) && $request->filled('guru_staf_id')) {
            $presensi->guru_staf_id = (string) $request->guru_staf_id;
        }

        try {
            DB::transaction(function () use ($presensi, $validated) {
                $presensi->update($validated);
            });

            $presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']);

            return response()->json([
                'success' => true,
                'message' => 'Presensi berhasil diperbarui.',
                'data'    => new PresensiResource($presensi),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update presensi', [
                'presensi_id' => (string) $presensi->id,
                'payload'     => $validated,
                'error'       => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui presensi.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Presensi $presensi): JsonResponse
    {
        try {
            $this->authorize('view', $presensi);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        $presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']);

        return response()->json([
            'success' => true,
            'data'    => new PresensiResource($presensi),
        ], Response::HTTP_OK);
    }

    public function destroy(Presensi $presensi): JsonResponse
    {
        try {
            $this->authorize('delete', $presensi);
        } catch (AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::transaction(fn() => $presensi->delete());

            return response()->json([
                'success' => true,
                'message' => 'Data presensi dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete presensi', ['presensi_id' => (string) $presensi->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus presensi.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
