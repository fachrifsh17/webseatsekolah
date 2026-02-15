<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{MataPelajaran, GuruStaf, ProfilSekolah, DataKontak};
use App\Http\Resources\MapelResource;
use App\Exports\MapelExport;
use Illuminate\Support\Facades\{Log, Auth, DB};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MapelController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'export']);
        $this->authorizeResource(MataPelajaran::class, 'mata_pelajaran');
    }

    private function getJurusanId()
    {
        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        return $guruStaf?->jurusan_id;
    }

    private function applyFilters(Request $request, $query, $jurusanId)
    {
        if (!$request->boolean('include_inactive')) {
            $query->where('is_active', 1);
        }

        if ($jurusanId) {
            $query->where('jurusan_id', $jurusanId);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('search')) {
            $query->where('nama_mapel', 'like', "%{$request->search}%");
        }

        if ($request->filled('tipe_mapel')) {
            $query->where('tipe_mapel', $request->tipe_mapel);
        }

        if ($request->filled('kategori_mapel')) {
            $query->where('kategori_mapel', $request->kategori_mapel);
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $jurusanId = $this->getJurusanId();
            
            $query = MataPelajaran::with('jurusan');
            $query = $this->applyFilters($request, $query, $jurusanId);

            $perPage = $request->query('per_page', 12);
            $data = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Daftar mata pelajaran jurusan berhasil dimuat',
                'data'    => MapelResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Index Mapel Jurusan Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar mata pelajaran',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $jurusanId = $this->getJurusanId();

        if (!$jurusanId) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'nama_mapel'     => 'required|string|max:255',
            'kode_mapel'     => 'required|string|unique:mata_pelajaran,kode_mapel',
            'tipe_mapel'     => 'required|in:Nasional,Kewilayahan,Kejuruan',
            'kategori_mapel' => 'required|string',
            'is_active'      => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            $data = $request->all();
            $data['jurusan_id'] = $jurusanId;
            
            $mapel = MataPelajaran::create($data);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Mata pelajaran berhasil ditambahkan',
                'data'    => new MapelResource($mapel->load('jurusan'))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Store Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan mata pelajaran'], 500);
        }
    }

    public function show(MataPelajaran $mataPelajaran): JsonResponse
    {
        $jurusanId = $this->getJurusanId();

        if (!$jurusanId || $mataPelajaran->jurusan_id !== $jurusanId) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Mata pelajaran ini bukan milik jurusan Anda.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data'    => new MapelResource($mataPelajaran->load('jurusan'))
        ], Response::HTTP_OK);
    }

    public function update(Request $request, MataPelajaran $mataPelajaran): JsonResponse
    {
        $jurusanId = $this->getJurusanId();

        if (!$jurusanId || $mataPelajaran->jurusan_id !== $jurusanId) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'nama_mapel' => 'sometimes|string|max:255',
            'kode_mapel' => 'sometimes|string|unique:mata_pelajaran,kode_mapel,' . $mataPelajaran->id,
        ]);

        try {
            $mataPelajaran->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Mata pelajaran berhasil diperbarui',
                'data'    => new MapelResource($mataPelajaran->load('jurusan'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui mata pelajaran'], 500);
        }
    }

    public function destroy(MataPelajaran $mataPelajaran): JsonResponse
    {
        $jurusanId = $this->getJurusanId();

        if (!$jurusanId || $mataPelajaran->jurusan_id !== $jurusanId) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $mataPelajaran->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mata pelajaran berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menghapus mata pelajaran'], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $this->authorize('viewAny', MataPelajaran::class);
            $jurusanId = $this->getJurusanId();

            if (!$jurusanId) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
            }

            $filters = [
                'jurusan_id'     => $jurusanId,
                'search'         => $request->query('search'),
                'tipe_mapel'     => $request->query('tipe_mapel'),
                'kategori_mapel' => $request->query('kategori_mapel'),
                'include_inactive' => $request->boolean('include_inactive'),
            ];

            $profil = ProfilSekolah::first() ?? new ProfilSekolah();
            $kontak = DataKontak::first() ?? new DataKontak();
            $fileName = 'Data_Mapel_Jurusan_' . date('Ymd_His') . '.xlsx';

            return Excel::download(new MapelExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Mapel Jurusan Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data'], 500);
        }
    }
}