<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{GuruMapel, JamSekolah, GuruStaf, ProfilSekolah, DataKontak};
use App\Http\Resources\GuruMapelResource;
use App\Exports\GuruMapelExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class GuruMapelController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->authorizeResource(GuruMapel::class, 'guru_mapel');
    }

    public function index(Request $request): JsonResponse
    {
        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);
        $query = $this->applyFilters($request, $query);

        $perPage = $request->query('per_page', 20);
        $data = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar penugasan guru jurusan berhasil dimuat.',
            'data'    => GuruMapelResource::collection($data)->response()->getData(true),
        ], Response::HTTP_OK);
    }

    public function show(GuruMapel $guruMapel): JsonResponse
    {
        $this->checkJurusanAccess($guruMapel);

        $guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);
        return response()->json([
            'success' => true,
            'data'    => new GuruMapelResource($guruMapel)
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', GuruMapel::class);

        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        
        if (!$guruStaf?->jurusan_id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']);
        $query = $this->applyFilters($request, $query);

        $profil = ProfilSekolah::first();
        $kontak = DataKontak::first();

        $filename = 'Data_Penugasan_Guru_' . Str::slug($guruStaf->jurusan->nama_jurusan ?? 'Jurusan') . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new GuruMapelExport($query, $profil, $kontak), $filename);
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

    private function applyFilters(Request $request, $query)
    {
        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        $jurusanId = $guruStaf?->jurusan_id;

        if ($jurusanId) {
            $query->whereHas('mapel', fn($q) => $q->where('jurusan_id', $jurusanId));
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

    private function checkJurusanAccess(GuruMapel $guruMapel)
    {
        $user = Auth::user();
        $guruStaf = GuruStaf::where('user_id', $user->id)->first();
        
        if (!$guruStaf || $guruMapel->mapel->jurusan_id !== $guruStaf->jurusan_id) {
            abort(Response::HTTP_FORBIDDEN, 'Anda tidak diizinkan melihat data dari jurusan lain.');
        }
    }
}