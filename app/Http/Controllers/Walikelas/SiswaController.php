<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, User, TahunAjaran};
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log, Hash};
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'export']);
    }

    private function getIdentity(): array
    {
        $user = Auth::user();
        $guru = $user->guruStaf; 

        if (!$guru) return [null, null, null];

        $taAktif = TahunAjaran::where('is_active', true)->first();
        if (!$taAktif) return [$guru, null, null];

        $kelas = Kelas::where('wali_kelas_id', $guru->id)
            ->where('tahun_ajaran_id', $taAktif->id)
            ->where('is_active', 1)
            ->first();

        return [$guru, $kelas, $taAktif];
    }

    private function applyFilters(Request $request, $query, $kelasId)
    {
        $query->where('kelas_id', $kelasId)->where('is_active', 1);

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
            [$guru, $kelas, $taAktif] = $this->getIdentity();

            if (!$kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki kelas perwalian aktif di tahun ajaran ini.'
                ], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::with(['user', 'kelas.jurusan']);
            $query = $this->applyFilters($request, $query, $kelas->id);

            $perPage = min((int) $request->get('per_page', 20), 100);
            $paginatedData = $query->orderBy('nama_lengkap', 'asc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => "Data siswa kelas {$kelas->nama_kelas} ({$taAktif->nama}) berhasil diambil.",
                'data'    => SiswaResource::collection($paginatedData),
                'meta'    => [
                    'current_page' => $paginatedData->currentPage(),
                    'last_page'    => $paginatedData->lastPage(),
                    'per_page'     => $paginatedData->perPage(),
                    'total'        => $paginatedData->total(),
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

    public function store(Request $request): JsonResponse
    {
        [$guru, $kelas, $taAktif] = $this->getIdentity();
        
        if (!$kelas) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak. Kelas perwalian aktif tidak ditemukan.'
            ], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nisn'         => 'required|string|unique:siswa,nisn',
            'email'        => 'required|email|unique:users,email',
            'jenis_kelamin'=> 'required|in:L,P',
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name'     => $request->nama_lengkap,
                'email'    => $request->email,
                'password' => Hash::make($request->nisn),
                'role'     => 'Siswa'
            ]);

            $siswa = Siswa::create(array_merge($request->all(), [
                'user_id'   => $user->id,
                'kelas_id'  => $kelas->id,
                'is_active' => 1
            ]));

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil ditambahkan ke kelas perwalian Anda.',
                'data'    => new SiswaResource($siswa->load('user', 'kelas'))
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Store Siswa Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menambah data siswa.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Siswa $siswa): JsonResponse
    {
        try {
            [$guru, $kelas, $taAktif] = $this->getIdentity();
            
            if (!$kelas || $siswa->kelas_id !== $kelas->id) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Akses ditolak. Siswa tidak terdaftar di kelas perwalian aktif Anda.'
                ], Response::HTTP_FORBIDDEN);
            }

            $siswa->load(['user', 'kelas.jurusan']);
            return response()->json([
                'success' => true,
                'data'    => new SiswaResource($siswa)
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail siswa.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, Siswa $siswa): JsonResponse
    {
        [$guru, $kelas, $taAktif] = $this->getIdentity();
        
        if (!$kelas || $siswa->kelas_id !== $kelas->id) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak.'
            ], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'nisn'         => 'sometimes|string|unique:siswa,nisn,' . $siswa->id,
        ]);

        try {
            DB::beginTransaction();
            $siswa->update($request->all());
            
            if ($request->filled('nama_lengkap')) {
                $siswa->user->update(['name' => $request->nama_lengkap]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diperbarui.',
                'data'    => new SiswaResource($siswa->load('user'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memperbarui data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Siswa $siswa): JsonResponse
    {
        [$guru, $kelas, $taAktif] = $this->getIdentity();
        
        if (!$kelas || $siswa->kelas_id !== $kelas->id) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak.'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::beginTransaction();
            $user = $siswa->user;
            $siswa->delete();
            if ($user) $user->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data siswa dan akun terkait berhasil dihapus.'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menghapus data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            [$guru, $kelas, $taAktif] = $this->getIdentity();

            if (!$kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal ekspor: Kelas perwalian aktif tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::query();
            $query = $this->applyFilters($request, $query, $kelas->id);

            $profil = DB::table('profil_sekolah')->first();
            $kontak = DB::table('data_kontak')->first();
            
            $fileName = 'Data_Siswa_' . 
                        str_replace(' ', '_', $kelas->nama_kelas) . '_' . 
                        str_replace(['/', ' '], '_', $taAktif->nama) . '_' . 
                        $taAktif->semester . '.xlsx';

            return Excel::download(
                new SiswaExport($query, $profil, $kontak, $kelas->nama_kelas), 
                $fileName
            );

        } catch (Throwable $e) {
            Log::error('Walikelas Siswa Export Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}