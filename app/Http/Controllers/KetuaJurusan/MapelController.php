<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{MataPelajaran, GuruStaf, ProfilSekolah, DataKontak};
use App\Http\Resources\MapelResource;
use App\Exports\MapelExport;
use Illuminate\Support\Facades\{Log, Auth};
use Illuminate\Http\{JsonResponse, Request};
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
        $this->authorizeResource(MataPelajaran::class, 'mata_pelajaran');
    }

    private function getJurusanId()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        return $guruStaf?->jurusan_id;
    }

    private function applyFilters(Request $request, $query, $jurusanId)
    {
        if (!$request->has('show_all')) {
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
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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

    public function export(Request $request)
    {
        try {
            $this->authorize('viewAny', MataPelajaran::class);
            $jurusanId = $this->getJurusanId();

            if (!$jurusanId) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Akses ditolak. Anda tidak terdaftar di jurusan manapun.'
                ], Response::HTTP_FORBIDDEN);
            }

            $filters = [
                'jurusan_id'     => $jurusanId,
                'search'         => $request->query('search'),
                'tipe_mapel'     => $request->query('tipe_mapel'),
                'kategori_mapel' => $request->query('kategori_mapel'),
            ];

            $profil = ProfilSekolah::first() ?? new ProfilSekolah();
            $kontak = DataKontak::first() ?? new DataKontak();

            $fileName = 'Data_Mapel_Jurusan_' . date('Ymd_His') . '.xlsx';

            return Excel::download(new MapelExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Mapel Jurusan Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengekspor data mata pelajaran',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}