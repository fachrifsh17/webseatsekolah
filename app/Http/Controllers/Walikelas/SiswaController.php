<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\User;
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
    }

    private function applyFilters(Request $request, $query)
    {
        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;

        $kelas = Kelas::where('wali_kelas_id', $guruStafId)->where('is_active', true)->first();

        if ($kelas) {
            $query->where('kelas_id', $kelas->id);
        } else {
            $query->whereRaw('1 = 0');
        }

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
        $query = Siswa::with(['user', 'kelas.jurusan', 'orangtua']);
        $query = $this->applyFilters($request, $query);

        $data = $query->orderBy('nama_lengkap', 'asc')->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => SiswaResource::collection($data)->response()->getData(true),
        ], Response::HTTP_OK);
    }

    public function show(Siswa $siswa): JsonResponse
    {
        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;
        
        $isWali = Kelas::where('id', $siswa->kelas_id)
                       ->where('wali_kelas_id', $guruStafId)
                       ->exists();

        if (!$isWali) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak.'
            ], Response::HTTP_FORBIDDEN);
        }

        $siswa->load(['user', 'kelas.jurusan', 'orangtua']);
        return response()->json([
            'success' => true,
            'data'    => new SiswaResource($siswa)
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;
        
        $kelas = Kelas::where('wali_kelas_id', $guruStafId)->first();
        $namaKelas = $kelas ? $kelas->nama_kelas : 'Kelas';

        $query = Siswa::query();
        $query = $this->applyFilters($request, $query);

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        return Excel::download(
            new SiswaExport($query, $profil, $kontak, $namaKelas), 
            'Data_Siswa_' . str_replace(' ', '_', $namaKelas) . '_' . date('Ymd_His') . '.xlsx'
        );
    }
}