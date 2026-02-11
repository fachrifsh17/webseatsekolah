<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Orangtua, Kelas};
use App\Http\Resources\OrangtuaResource;
use App\Exports\OrangtuaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Log};
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Throwable;

class OrangtuaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
    }

    private function getKelasPerwalian()
    {
        $guruStafId = Auth::user()->guruStaf?->id;

        return Kelas::where('wali_kelas_id', $guruStafId)
            ->where('is_active', true)
            ->first();
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

            $perPage = min((int) $request->get('per_page', 20), 100);
            $data = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => "Daftar orang tua siswa kelas {$kelas->nama_kelas} berhasil diambil.",
                'data'    => OrangtuaResource::collection($data),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => (int) $data->perPage(),
                    'total'        => $data->total(),
                ]
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Gagal mengambil daftar orang tua: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data orang tua.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Orangtua $orangtua): JsonResponse
    {
        try {
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

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail orang tua.',
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

            return Excel::download(
                new OrangtuaExport($query, $profil, $kontak, $kelas), 
                $filename
            );

        } catch (Throwable $e) {
            Log::error('Gagal ekspor data orang tua: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}