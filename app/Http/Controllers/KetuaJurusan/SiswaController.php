<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\User;
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class SiswaController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->authorizeResource(Siswa::class, 'siswa');
    }

    private function getJurusanKetua()
    {
        /** @var User $user */
        $user = Auth::user();
        $guruStaf = $user->guruStaf;

        if (!$guruStaf || !$guruStaf->jurusan_id) {
            return null;
        }

        return $guruStaf->jurusan;
    }

    public function index(Request $request): JsonResponse
    {
        $jurusan = $this->getJurusanKetua();

        if (!$jurusan) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak terdaftar sebagai Ketua Jurusan.'
            ], Response::HTTP_FORBIDDEN);
        }

        $query = Siswa::with(['user', 'kelas.jurusan', 'orangtua'])
            ->whereHas('kelas', function ($q) use ($jurusan) {
                $q->where('jurusan_id', $jurusan->id);
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhereHas('kelas', function ($qK) use ($search) {
                      $qK->where('nama_kelas', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        $perPage = $request->get('per_page', 20);
        $data = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar siswa jurusan ' . $jurusan->nama_jurusan . ' berhasil diambil.',
            'data'    => SiswaResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(Siswa $siswa): JsonResponse
    {
        $jurusan = $this->getJurusanKetua();

        if (!$jurusan || $siswa->kelas->jurusan_id !== $jurusan->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Siswa ini bukan dari jurusan Anda.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data'    => new SiswaResource($siswa->load(['user', 'kelas.jurusan', 'orangtua']))
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Siswa::class);
        $jurusan = $this->getJurusanKetua();

        if (!$jurusan) {
            abort(403, 'Akses ditolak.');
        }

        $query = Siswa::query()->whereHas('kelas', function ($q) use ($jurusan) {
            $q->where('jurusan_id', $jurusan->id);
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        $filename = 'Data_Siswa_' . Str::slug($jurusan->nama_jurusan) . '_' . now()->format('Ymd_His') . '.xlsx';

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        return Excel::download(
            new SiswaExport($query, $profil, $kontak, $jurusan), 
            $filename
        );
    }
}