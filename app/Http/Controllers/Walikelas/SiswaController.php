<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, TahunAjaran};
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log};
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
        $this->middleware('log.aktivitas')->only(['export']);
    }

    private function getIdentity(): array
    {
        $user = Auth::user();
        $guru = $user->guruStaf; 

        if (!$guru) return [null, null, null];

        $semesterAktif = DB::table('semesters')
            ->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
            ->where('semesters.is_active', 1)
            ->select('semesters.*', 'tahun_ajaran.nama as nama_tahun')
            ->first();

        if (!$semesterAktif) return [$guru, null, null];

        $kelas = Kelas::with(['jurusan', 'tingkatan'])
            ->whereHas('kelasWali', function ($q) use ($guru) {
                $q->where('guru_staf_id', $guru->id)
                  ->where('is_active', 1);
            })
            ->where('is_active', 1)
            ->first();

        return [$guru, $kelas, $semesterAktif];
    }

    private function applyFilters(Request $request, $query, $kelasId, $semesterId)
    {
        $isActive = $request->query('is_active', 1);

        $query->where('is_active', $isActive);

        $query->whereHas('riwayatKelas', function ($q) use ($kelasId, $semesterId, $isActive) {
            $q->where('kelas_id', $kelasId)
              ->where('semester_id', $semesterId)
              ->where('is_active', $isActive);
        });

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
            [$guru, $kelas, $semesterAktif] = $this->getIdentity();

            if (!$kelas || !$semesterAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data perwalian atau semester aktif tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::with(['user', 'orangtua']);
            $query = $this->applyFilters($request, $query, $kelas->id, $semesterAktif->id);

            $perPage = min((int) $request->query('per_page', 20), 100);
            $paginatedData = $query->orderBy('nama_lengkap', 'asc')->paginate($perPage);

            $transformedData = collect($paginatedData->items())->map(function($siswa) {
                $resource = (new SiswaResource($siswa))->toArray(request());
                unset($resource['kelas']);
                return $resource;
            });

            return response()->json([
                'success' => true,
                'message' => "Daftar siswa berhasil dimuat.",
                'info'    => [
                    'id_kelas'    => $kelas->id,
                    'nama_kelas'  => $kelas->nama_kelas,
                    'tingkat'     => $kelas->tingkatan->nama_tingkat ?? null,
                    'jurusan'     => $kelas->jurusan->nama_jurusan ?? null,
                    'tahun_aktif' => $semesterAktif->nama_tahun . " (" . $semesterAktif->nama . ")"
                ],
                'data'    => $transformedData,
                'meta'    => [
                    'current_page' => $paginatedData->currentPage(),
                    'last_page'    => $paginatedData->lastPage(),
                    'per_page'     => $paginatedData->perPage(),
                    'total'        => $paginatedData->total(),
                    'has_more'     => $paginatedData->hasMorePages(),
                    'next_page_url' => $paginatedData->nextPageUrl(),
                    'prev_page_url' => $paginatedData->previousPageUrl(),
                ]
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Walikelas Siswa Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data siswa.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            [$guru, $kelas, $semesterAktif] = $this->getIdentity();

            if (!$kelas || !$semesterAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak.'
                ], Response::HTTP_FORBIDDEN);
            }

            $siswa = Siswa::with(['user', 'orangtua', 'riwayatKelas' => function($q) use ($semesterAktif) {
                $q->where('semester_id', $semesterAktif->id)->where('is_active', 1)->with('kelas');
            }])
            ->whereHas('riwayatKelas', function ($q) use ($kelas, $semesterAktif) {
                $q->where('kelas_id', $kelas->id)
                  ->where('semester_id', $semesterAktif->id)
                  ->where('is_active', 1);
            })
            ->where('is_active', 1)
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => "Detail siswa berhasil dimuat.",
                'data'    => new SiswaResource($siswa)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Walikelas Siswa Show Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function export(Request $request)
    {
        try {
            [$guru, $kelas, $semesterAktif] = $this->getIdentity();

            if (!$kelas || !$semesterAktif) {
                return response()->json(['success' => false, 'message' => 'Data tidak lengkap.'], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::query();
            $query = $this->applyFilters($request, $query, $kelas->id, $semesterAktif->id);
            $query->orderBy('nama_lengkap', 'asc');

            // 1. Format Nama Kelas (Spasi dan Strip diganti Underscore)
            $namaKelas = strtoupper(str_replace([' ', '-'], '_', $kelas->nama_kelas));

            // 2. Format Tahun Ajaran (Semua karakter non-angka diganti underscore agar jadi 2025_2026)
            $namaTahun = preg_replace('/[^0-9]/', '_', $semesterAktif->nama_tahun);

            // 3. Format Semester (Ganti spasi menjadi Underscore)
            $namaSemester = strtoupper(str_replace(' ', '_', $semesterAktif->nama));
            
            // 4. Status Aktif
            $isActive = $request->query('is_active', 1);
            $statusStr = $isActive ? 'AKTIF' : 'TIDAK_AKTIF';

            // Hasil Akhir: DATA_SISWA_X_RPL_1_2025_2026_GENAP_AKTIF.xlsx
            $fileName = "DATA_SISWA_{$namaKelas}_{$namaTahun}_{$namaSemester}_{$statusStr}.xlsx";

            if (ob_get_contents()) ob_end_clean();

            return Excel::download(
                new SiswaExport(
                    $query, 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(), 
                    $kelas,
                    ['is_active' => $isActive, 'semester_id' => $semesterAktif->id]
                ), 
                $fileName
            );

        } catch (Throwable $e) {
            Log::error('Walikelas Siswa Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal ekspor data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}