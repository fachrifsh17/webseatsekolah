<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\GuruStaf;
use App\Http\Resources\MapelResource;
use App\Exports\MapelExport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class MapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
    }

    private function applyFilters(Request $request, $query)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        $jurusanId = $guruStaf?->jurusan_id;

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
            $query = MataPelajaran::with('jurusan');
            $query = $this->applyFilters($request, $query);

            $perPage = $request->query('per_page', 20);
            $data = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Daftar mata pelajaran jurusan berhasil dimuat',
                'data'    => MapelResource::collection($data)->response()->getData(true),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Index Mapel Jurusan Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar mata pelajaran'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $guruStaf = GuruStaf::where('user_id', $user->id)->first();
            $jurusanId = $guruStaf?->jurusan_id;

            if (!$jurusanId) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Akses ditolak'
                ], Response::HTTP_FORBIDDEN);
            }

            $filters = [
                'jurusan_id'     => $jurusanId,
                'search'         => $request->query('search'),
                'tipe_mapel'     => $request->query('tipe_mapel'),
                'kategori_mapel' => $request->query('kategori_mapel'),
            ];

            return Excel::download(new MapelExport($filters), 'Data_Mapel_Jurusan_' . date('Ymd_His') . '.xlsx');
        } catch (Throwable $e) {
            Log::error('Export Mapel Jurusan Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengekspor data'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}