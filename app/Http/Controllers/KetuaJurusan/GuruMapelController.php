<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\GuruMapel;
use App\Models\JamSekolah;
use App\Models\GuruStaf;
use App\Models\ProfilSekolah;
use App\Models\DataKontak; // Nama model diganti di sini
use App\Http\Resources\GuruMapelResource;
use App\Exports\GuruMapelExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class GuruMapelController extends Controller
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
            $query->whereHas('mapel', function ($q) use ($jurusanId) {
                $q->where('jurusan_id', $jurusanId);
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('guru', fn($g) => $g->where('nama', 'LIKE', "%{$search}%")->orWhere('nip', 'LIKE', "%{$search}%"))
                  ->orWhereHas('mapel', fn($m) => $m->where('nama_mapel', 'LIKE', "%{$search}%"))
                  ->orWhereHas('kelas', fn($k) => $k->where('nama_kelas', 'LIKE', "%{$search}%"));
            });
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);
        $query = $this->applyFilters($request, $query);

        $perPage = $request->query('per_page', 20);
        $data = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar penugasan guru jurusan berhasil dimuat',
            'data'    => GuruMapelResource::collection($data)->response()->getData(true),
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        $jurusanId = $guruStaf?->jurusan_id;

        if (!$jurusanId) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], Response::HTTP_FORBIDDEN);
        }

        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);
        $query = $this->applyFilters($request, $query);

        $profil = ProfilSekolah::first();
        $kontak = DataKontak::first(); // Menggunakan model DataKontak

        $filename = 'Data_Penugasan_Guru';
        if ($guruStaf->jurusan) {
            $filename .= '_' . Str::slug($guruStaf->jurusan->nama_jurusan);
        }
        $filename .= '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new GuruMapelExport($query, $profil, $kontak), 
            $filename
        );
    }

    public function show(GuruMapel $guruMapel): JsonResponse
    {
        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        $jurusanId = $guruStaf?->jurusan_id;

        if (!$jurusanId || $guruMapel->mapel->jurusan_id !== $jurusanId) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);
        return response()->json([
            'success' => true,
            'data'    => new GuruMapelResource($guruMapel)
        ], Response::HTTP_OK);
    }

    public function getJamByHari(Request $request): JsonResponse
    {
        $hari = $request->query('hari');
        $jam = JamSekolah::where('hari', $hari)->orderBy('waktu_mulai')->get();

        return response()->json([
            'success' => true,
            'data'    => $jam
        ], Response::HTTP_OK);
    }
}