<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{MataPelajaran, GuruStaf, ProfilSekolah, DataKontak, TahunAjaran};
use App\Http\Resources\MapelResource;
use App\Exports\MapelExport;
use Illuminate\Support\Facades\{Log, Auth, DB};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Throwable;

class MapelController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['export']);
    }

    private function getJurusanId()
    {
        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        return $guruStaf?->jurusan_id;
    }

    private function getNamaJurusan()
    {
        $user = Auth::user();
        return $user->guruStaf?->jurusan?->nama_jurusan;
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

            $perPage = (int) $request->query('per_page', 12);
            $data = $query->latest()->paginate($perPage);
            
            $paginationData = $data->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Daftar mata pelajaran jurusan berhasil dimuat',
                'data'    => MapelResource::collection($data),
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
            Log::error('Index Mapel Jurusan Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar mata pelajaran',
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
            $namaJurusan = $this->getNamaJurusan();
            $taAktif = TahunAjaran::where('is_active', 1)->first();

            if (!$jurusanId) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
            }

            $filters = [
                'jurusan_id'       => $jurusanId,
                'search'           => $request->query('search'),
                'tipe_mapel'       => $request->query('tipe_mapel'),
                'kategori_mapel'   => $request->query('kategori_mapel'),
                'is_active'        => $request->boolean('include_inactive') ? null : 1,
            ];

            $nameParts = ['DATA_MAPEL'];
            
            if ($namaJurusan) {
                $nameParts[] = strtoupper(str_replace([' ', '-'], '_', $namaJurusan));
            }

            if ($taAktif) {
                $namaTa = strtoupper(str_replace([' ', '-', '/'], '_', $taAktif->nama));
                $semester = strtoupper($taAktif->semester ?? '');
                $nameParts[] = $namaTa;
                if ($semester) {
                    $nameParts[] = $semester;
                }
            }

            $nameParts[] = $request->boolean('include_inactive') ? 'SEMUA' : 'AKTIF';

            $fileName = implode('_', $nameParts) . '.xlsx';

            $profil = ProfilSekolah::first() ?? new ProfilSekolah();
            $kontak = DataKontak::first() ?? new DataKontak();
            
            if (ob_get_contents()) ob_end_clean();

            return Excel::download(new MapelExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Mapel Jurusan Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}