<?php

namespace App\Http\Controllers\Kurikulum;

use App\Http\Controllers\Controller;
use App\Models\{MataPelajaran, User, ProfilSekolah, DataKontak};
use App\Http\Resources\MapelResource;
use App\Http\Requests\{StoreMapelRequest, UpdateMapelRequest};
use App\Exports\MapelExport;
use App\Imports\MapelImport;
use Illuminate\Support\Facades\{DB, Log, Auth};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class MapelController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'import']);

        $this->authorizeResource(MataPelajaran::class, 'mapel');
    }

    private function getUserAccess()
    {
        $user = User::with(['guruStaf.strukturJabatan.jabatan'])->find(Auth::id());
        $guruStaf = $user?->guruStaf;
        $namaJabatan = $guruStaf?->strukturJabatan?->jabatan?->nama_jabatan;

        $isFullAccess = $user->hasRole('Admin') || in_array($namaJabatan, ['Waka Kurikulum', 'Kurikulum']);

        return [
            'isFullAccess' => $isFullAccess,
            'guruStaf' => $guruStaf,
            'namaJabatan' => $namaJabatan
        ];
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $access = $this->getUserAccess();
            $query = MataPelajaran::with('jurusan');

            if (!$request->has('show_all')) {
                $query->where('is_active', 1);
            }

            if (!$access['isFullAccess']) {
                if ($access['namaJabatan'] === 'Ketua Jurusan' && $access['guruStaf']) {
                    $query->where('jurusan_id', $access['guruStaf']->jurusan_id);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } else {
                if ($request->filled('jurusan_id')) {
                    $query->where('jurusan_id', $request->jurusan_id);
                }
            }

            if ($request->filled('tipe_mapel')) {
                $query->where('tipe_mapel', $request->tipe_mapel);
            }

            if ($request->filled('kategori_mapel')) {
                $query->where('kategori_mapel', $request->kategori_mapel);
            }

            if ($request->filled('search')) {
                $query->where('nama_mapel', 'like', "%{$request->search}%");
            }

            $data = $query->latest()->paginate(12);

            return response()->json([
                'success' => true,
                'data'    => MapelResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch mata pelajaran', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(MataPelajaran $mapel): JsonResponse
    {
        try {
            $access = $this->getUserAccess();

            if (!$access['isFullAccess']) {
                if ($access['namaJabatan'] === 'Ketua Jurusan') {
                    if ($mapel->jurusan_id !== $access['guruStaf']?->jurusan_id) {
                        return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                    }
                } else {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                }
            }

            return response()->json([
                'success' => true,
                'data'    => new MapelResource($mapel->load('jurusan'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch mata pelajaran detail', ['mapel_id' => (string) $mapel->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreMapelRequest $request): JsonResponse
    {
        $access = $this->getUserAccess();
        $validated = $request->validated();

        if (!$access['isFullAccess']) {
            if ($access['namaJabatan'] === 'Ketua Jurusan' && $access['guruStaf']) {
                $validated['jurusan_id'] = $access['guruStaf']->jurusan_id;
                $validated['kategori_mapel'] = 'produktif';
                $validated['tipe_mapel'] = 'khusus';
            } else {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
            }
        }

        if (MataPelajaran::where('nama_mapel', $validated['nama_mapel'])
            ->where('jurusan_id', $validated['jurusan_id'] ?? null)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama mata pelajaran sudah terdaftar di jurusan ini.',
                'errors'  => ['nama_mapel' => ['Nama mata pelajaran sudah terdaftar.']]
            ], Response::HTTP_CONFLICT);
        }

        try {
            $item = DB::transaction(fn() => MataPelajaran::create($validated));

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil ditambahkan.',
                'data'    => new MapelResource($item->load('jurusan'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create mata pelajaran', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateMapelRequest $request, MataPelajaran $mapel): JsonResponse
    {
        $access = $this->getUserAccess();
        $validated = $request->validated();

        if (!$access['isFullAccess']) {
            if ($access['namaJabatan'] === 'Ketua Jurusan') {
                if ($mapel->jurusan_id !== $access['guruStaf']?->jurusan_id) {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                }
                $validated['jurusan_id'] = $access['guruStaf']->jurusan_id;
                $validated['kategori_mapel'] = 'produktif';
                $validated['tipe_mapel'] = 'khusus';
            } else {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
            }
        }

        if (!empty($validated['nama_mapel']) &&
            MataPelajaran::where('nama_mapel', $validated['nama_mapel'])
                ->where('jurusan_id', $validated['jurusan_id'] ?? $mapel->jurusan_id)
                ->where('id', '<>', $mapel->id)
                ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nama mata pelajaran sudah terdaftar.',
                'errors'  => ['nama_mapel' => ['Nama mata pelajaran sudah terdaftar.']]
            ], Response::HTTP_CONFLICT);
        }

        try {
            DB::transaction(fn() => $mapel->update($validated));
            $mapel->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil diperbarui.',
                'data'    => new MapelResource($mapel->load('jurusan'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update mata pelajaran', ['mapel_id' => (string) $mapel->id, 'payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(MataPelajaran $mapel): JsonResponse
    {
        try {
            $access = $this->getUserAccess();

            if (!$access['isFullAccess']) {
                if ($access['namaJabatan'] === 'Ketua Jurusan') {
                    if ($mapel->jurusan_id !== $access['guruStaf']?->jurusan_id) {
                        return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                    }
                } else {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                }
            }

            DB::transaction(fn() => $mapel->update(['is_active' => 0]));

            return response()->json([
                'success'      => true,
                'message'      => 'Data mata pelajaran berhasil dinonaktifkan',
                'notification' => 'Berhasil dinonaktifkan'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to disable mata pelajaran', ['mapel_id' => (string) $mapel->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menonaktifkan mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $access = $this->getUserAccess();
            $filters = [
                'jurusan_id'     => $request->query('jurusan_id'),
                'search'         => $request->query('search'),
                'tipe_mapel'     => $request->query('tipe_mapel'),
                'kategori_mapel' => $request->query('kategori_mapel'),
            ];

            if (!$access['isFullAccess']) {
                if ($access['namaJabatan'] === 'Ketua Jurusan' && $access['guruStaf']) {
                    $filters['jurusan_id'] = $access['guruStaf']->jurusan_id;
                } else {
                    return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
                }
            }

            $profil = ProfilSekolah::first() ?? new ProfilSekolah();
            $kontak = DataKontak::first() ?? new DataKontak();

            $fileName = 'Data_Mata_Pelajaran_' . now()->format('Ymd_His') . '.xlsx';

            return Excel::download(new MapelExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls']);

        try {
            $access = $this->getUserAccess();

            if (!$access['isFullAccess'] && $access['namaJabatan'] !== 'Ketua Jurusan') {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
            }

            Excel::import(new MapelImport($access), $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Data mata pelajaran berhasil diimport'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimport data. Pastikan format kolom sesuai.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}