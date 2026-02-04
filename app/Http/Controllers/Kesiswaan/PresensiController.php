<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Http\Requests\UpdatePresensiRequest;
use App\Http\Resources\PresensiResource;
use App\Exports\PresensiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PresensiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['update']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Presensi::class);

        $query = Presensi::query();
        $query = $this->applyPresensiFilters($request, $query);

        $perPage = min((int) $request->get('per_page', 50), 100);
        $data = $query->paginate($perPage);

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

    public function show(Presensi $presensi): JsonResponse
    {
        $this->authorize('view', $presensi);
        return response()->json([
            'success' => true, 
            'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
        ], Response::HTTP_OK);
    }

    public function update(UpdatePresensiRequest $request, Presensi $presensi): JsonResponse
    {
        $this->authorize('update', $presensi);
        try {
            DB::transaction(fn() => $presensi->update($request->validated()));
            return response()->json([
                'success' => true, 
                'message' => 'Data presensi berhasil diperbarui oleh Kesiswaan.',
                'data' => new PresensiResource($presensi->load(['siswa.kelas', 'guruStaf', 'tahunAjaran']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Presensi::class);
        
        $profil = DB::table('sekolah_setting')->first();
        $kontak = DB::table('data_kontak')->first();
        
        $month = $request->get('bulan', date('m'));
        $year = $request->get('tahun', date('Y'));
        
        $query = Presensi::query();
        $query->whereHas('siswa', fn($q) => $q->where('is_active', true));
        
        $filteredQuery = $this->applyPresensiFilters($request, $query);

        $taId = $request->get('tahun_ajaran_id') ?? TahunAjaran::where('is_active', true)->first()?->id;
        $taData = DB::table('tahun_ajaran')->where('id', $taId)->first();
        $taLabel = $taData ? $taData->nama . " (" . $taData->semester . ")" : '-';

        $dataKelas = $request->filled('kelas_id') ? Kelas::with('waliKelas')->find($request->kelas_id) : null;
        $namaKelas = $dataKelas ? $dataKelas->nama_kelas : "Semua_Kelas";

        return Excel::download(
            new PresensiExport($filteredQuery, $namaKelas, "Bulan-$month-$year", $profil, $kontak, $dataKelas, 'kesiswaan', $taLabel), 
            "Rekap_Presensi_Kesiswaan_Kelas_{$namaKelas}_Bulan_{$month}_{$year}.xlsx"
        );
    }

    private function applyPresensiFilters(Request $request, $query)
    {
        $taId = $request->tahun_ajaran_id ?? TahunAjaran::where('is_active', true)->first()?->id;
        if ($taId) $query->where('tahun_ajaran_id', $taId);

        if ($request->filled('kelas_id')) {
            $query->whereHas('siswa', fn($q) => $q->where('kelas_id', (string) $request->kelas_id));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('siswa', fn($qs) => $qs->where('nama_lengkap', 'like', "%{$search}%"))
                  ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('bulan') && $request->filled('tahun')) {
            $query->whereMonth('tanggal', (int) $request->bulan)
                  ->whereYear('tanggal', (int) $request->tahun);
        } elseif ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        } else {
            $query->whereMonth('tanggal', date('m'))
                  ->whereYear('tanggal', date('Y'));
        }

        return $query->with(['siswa.kelas', 'guruStaf', 'tahunAjaran'])->orderBy('tanggal', 'asc');
    }
}