<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas};
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Log};
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
    }

    /**
     * Helper untuk mendapatkan kelas perwalian aktif
     */
    private function getKelasPerwalian()
    {
        return Kelas::where('wali_kelas_id', Auth::user()->guruStaf?->id)
            ->where('is_active', true)
            ->first();
    }

    private function applyFilters(Request $request, $query, $kelasId)
    {
        // Filter berdasarkan kelas perwalian
        $query->where('kelas_id', $kelasId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $kelas = $this->getKelasPerwalian();

            if (!$kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki kelas perwalian aktif.'
                ], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::with(['user', 'kelas.jurusan', 'orangtua']);
            $query = $this->applyFilters($request, $query, $kelas->id);

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->orderBy('nama_lengkap', 'asc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => "Data siswa kelas {$kelas->nama_kelas} berhasil diambil.",
                'data'    => SiswaResource::collection($data)->response()->getData(true),
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Walikelas Siswa Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data siswa.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Siswa $siswa): JsonResponse
    {
        try {
            $kelas = $this->getKelasPerwalian();
            
            // Validasi apakah siswa yang diminta ada di kelas perwalian login
            if (!$kelas || $siswa->kelas_id !== $kelas->id) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Akses ditolak. Siswa tidak terdaftar di kelas perwalian Anda.'
                ], Response::HTTP_FORBIDDEN);
            }

            $siswa->load(['user', 'kelas.jurusan', 'orangtua']);
            return response()->json([
                'success' => true,
                'data'    => new SiswaResource($siswa)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail siswa.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $kelas = $this->getKelasPerwalian();

            if (!$kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal ekspor: Kelas perwalian tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::query();
            $query = $this->applyFilters($request, $query, $kelas->id);

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            $fileName = 'Data_Siswa_' . str_replace(' ', '_', $kelas->nama_kelas) . '_' . date('Ymd_His') . '.xlsx';

            return Excel::download(
                new SiswaExport($query, $profil, $kontak, $kelas->nama_kelas), 
                $fileName
            );

        } catch (Throwable $e) {
            Log::error('Walikelas Siswa Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}