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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class TahunAjaranController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);

        $this->authorizeResource(TahunAjaran::class, 'tahun_ajaran');
    }

    public function index(): JsonResponse
    {
        try {
            $perPage = min((int) request()->get('per_page', 12), 100);
            $items = TahunAjaran::with('kurikulum')->orderBy('nama', 'desc')->paginate($perPage);
            $paginationData = $items->toArray();

            return response()->json([
                'success' => true,
                'data'    => TahunAjaranResource::collection($items),
                'meta'    => [
                    'current_page'  => $paginationData['current_page'],
                    'last_page'     => $paginationData['last_page'],
                    'per_page'      => $paginationData['per_page'],
                    'total'         => $paginationData['total'],
                    'from'          => $paginationData['from'],
                    'to'            => $paginationData['to'],
                    'path'          => $paginationData['path'],
                    'next_page_url' => $paginationData['next_page_url'],
                    'prev_page_url' => $paginationData['prev_page_url'],
                    'links'         => array_map(function ($link) {
                        return [
                            'url'    => $link['url'],
                            'label'  => $link['label'],
                            'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
                            'active' => $link['active'],
                        ];
                    }, $paginationData['links']),
                ],
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
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran ' . $request->nama . ' sudah terdaftar.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $activeKurikulum = Kurikulum::where('is_active', true)->first();

            if (!$activeKurikulum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum aktif tidak ditemukan. Wajib membuat kurikulum aktif terlebih dahulu.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $tahunAjaran = DB::transaction(function () use ($request, $activeKurikulum) {
                $data = $request->validated();

                if (empty($data['kurikulum_id'])) {
                    $data['kurikulum_id'] = $activeKurikulum->id;
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
        return response()->json([
            'success' => true,
            'data'    => new TahunAjaranResource($tahunAjaran->load('kurikulum')),
        ], Response::HTTP_OK);
    }

    public function update(UpdateTahunAjaranRequest $request, TahunAjaran $tahunAjaran): JsonResponse
    {
        try {
            $exists = TahunAjaran::where('nama', $request->nama)
                ->where('id', '!=', $tahunAjaran->id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran ' . $request->nama . ' sudah digunakan data lain.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $activeKurikulum = Kurikulum::where('is_active', true)->first();

            if (!$activeKurikulum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kurikulum aktif tidak ditemukan. Wajib membuat kurikulum aktif terlebih dahulu.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::transaction(function () use ($request, $tahunAjaran, $activeKurikulum) {
                $data = $request->validated();

                if (empty($data['kurikulum_id'])) {
                    $data['kurikulum_id'] = $activeKurikulum->id;
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
            Log::error('Failed to update tahun ajaran', ['tahun_ajaran_id' => (string) $tahunAjaran->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui tahun ajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(TahunAjaran $tahunAjaran): JsonResponse
    {
        $relations = [
            'kelas' => 'Data Kelas',
            'presensi' => 'Data Presensi',
            'presensiGuruMapel' => 'Data Presensi Guru',
            'poinSiswa' => 'Data Poin Siswa',
            'jamSekolah' => 'Data Jam Sekolah'
        ];

        foreach ($relations as $method => $label) {
            if ($tahunAjaran->$method()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak dapat menghapus tahun ajaran karena masih memiliki relasi dengan {$label}."
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
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