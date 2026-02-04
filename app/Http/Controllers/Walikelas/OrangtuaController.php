<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\Orangtua;
use App\Models\Kelas;
use App\Http\Resources\OrangtuaResource;
use App\Exports\OrangtuaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class OrangtuaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
    }

    private function getKelasPerwalian()
    {
        $user = Auth::user();
        $guruStafId = $user->guruStaf?->id;

        return Kelas::where('wali_kelas_id', $guruStafId)
            ->where('is_active', true)
            ->first();
    }

    public function index(Request $request): JsonResponse
    {
        $kelas = $this->getKelasPerwalian();

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki kelas perwalian aktif.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Filter anak di level index juga agar konsisten
        $query = Orangtua::with(['user', 'anak' => function($q) use ($kelas) {
                $q->where('kelas_id', $kelas->id)->with('kelas.jurusan');
            }])
            ->whereHas('anak', function ($q) use ($kelas) {
                $q->where('kelas_id', $kelas->id);
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhereHas('anak', function ($qa) use ($search) {
                      $qa->where('nama_lengkap', 'like', "%{$search}%");
                  });
            });
        }

        $data = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Daftar orang tua siswa kelas ' . $kelas->nama_kelas . ' berhasil diambil.',
            'data'    => OrangtuaResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ]
        ], Response::HTTP_OK);
    }

    public function show(Orangtua $orangtua): JsonResponse
    {
        $kelas = $this->getKelasPerwalian();

        $isWali = $orangtua->anak()
            ->where('kelas_id', $kelas?->id)
            ->exists();

        if (!$isWali) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Orang tua ini bukan bagian dari kelas perwalian Anda.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data'    => new OrangtuaResource($orangtua->load(['user', 'anak' => function($q) use ($kelas) {
                $q->where('kelas_id', $kelas->id)->with('kelas.jurusan');
            }]))
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $kelas = $this->getKelasPerwalian();

        if (!$kelas) {
            abort(403, 'Anda tidak memiliki akses untuk mengekspor data kelas ini.');
        }

        // PENTING: Filter relasi anak agar hanya menampilkan anak di kelas perwalian tersebut
        $query = Orangtua::query()
            ->with(['anak' => function($q) use ($kelas) {
                $q->where('kelas_id', $kelas->id)->with('kelas.jurusan');
            }])
            ->whereHas('anak', function ($q) use ($kelas) {
                $q->where('kelas_id', $kelas->id);
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhereHas('anak', function ($qa) use ($search) {
                      $qa->where('nama_lengkap', 'like', "%{$search}%");
                  });
            });
        }

        $filename = 'Data_Orangtua_' . Str::slug($kelas->nama_kelas) . '_' . now()->format('Ymd_His') . '.xlsx';

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        // Kirimkan variabel $kelas sebagai parameter ke-4 ke OrangtuaExport
        return Excel::download(
            new OrangtuaExport($query, $profil, $kontak, $kelas), 
            $filename
        );
    }
}