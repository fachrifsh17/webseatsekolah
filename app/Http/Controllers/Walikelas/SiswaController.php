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

    private function applyFilters(Request $request, $query, $kelasId, $taId)
    {
        $query->whereHas('riwayatKelas', function ($q) use ($kelasId, $taId) {
            $q->where('kelas_id', $kelasId)
              ->where('tahun_ajaran_id', $taId);
        });

        $isActive = $request->get('is_active', 1);
        $query->where('is_active', $isActive);

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

            $query = Siswa::with(['user', 'orangtua']);
            $query = $this->applyFilters($request, $query, $kelas->id, $taAktif->id);

            $perPage = min((int) $request->get('per_page', 20), 100);
            $paginatedData = $query->orderBy('nama_lengkap', 'asc')->paginate($perPage);

            $transformedData = collect($paginatedData->items())->map(function($siswa) {
                $resource = (new SiswaResource($siswa))->toArray(request());
                
                // Menghapus data kelas dari tiap item siswa agar tidak duplikat
                unset($resource['kelas']);

                // Sederhanakan data orangtua (hanya nama)
                if (isset($resource['orangtua'])) {
                    $resource['orangtua'] = collect($resource['orangtua'])->map(function($ortu) {
                        return [
                            'nama_lengkap' => $ortu['nama_lengkap'] ?? null
                        ];
                    })->values();
                }

                return $resource;
            });

            return response()->json([
                'success' => true,
                'message' => "Daftar siswa berhasil dimuat.",
                'info'    => [
                    'id_kelas'    => $kelas->id,
                    'nama_kelas'  => $kelas->nama_kelas,
                    'tahun_aktif' => $taAktif->nama . " (" . $taAktif->semester . ")"
                ],
                'data'    => $transformedData,
                'meta'    => [
                    'current_page' => $paginatedData->currentPage(),
                    'last_page'    => $paginatedData->lastPage(),
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
                'message' => 'Akses ditolak. Anda tidak memiliki kelas perwalian aktif.'
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

            $siswa = Siswa::create(array_merge($request->except(['email']), [
                'user_id'   => $user->id,
                'is_active' => 1
            ]));

            $siswa->riwayatKelas()->create([
                'kelas_id' => $kelas->id,
                'tahun_ajaran_id' => $taAktif->id,
                'is_active' => 1
            ]);

            DB::commit();
            
            $result = (new SiswaResource($siswa->load(['user', 'orangtua'])))->toArray($request);
            
            // Konsistensi: Hapus key kelas dari individual data dan filter ortu
            unset($result['kelas']);
            if (isset($result['orangtua'])) {
                $result['orangtua'] = collect($result['orangtua'])->map(fn($o) => ['nama_lengkap' => $o['nama_lengkap'] ?? null])->values();
            }

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil ditambahkan.',
                'info'    => [
                    'id_kelas'   => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas
                ],
                'data'    => $result
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Store Siswa Wali Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menambah data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Siswa $siswa): JsonResponse
    {
        [$guru, $kelas, $taAktif] = $this->getIdentity();
        
        if (!$kelas) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $isMember = $siswa->riwayatKelas()
            ->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', $taAktif->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $result = (new SiswaResource($siswa->load(['user', 'orangtua'])))->toArray(request());
        
        // Hapus key kelas dari data individual
        unset($result['kelas']);
        
        if (isset($result['orangtua'])) {
            $result['orangtua'] = collect($result['orangtua'])->map(fn($o) => ['nama_lengkap' => $o['nama_lengkap'] ?? null])->values();
        }

        return response()->json([
            'success' => true,
            'info'    => [
                'id_kelas'   => $kelas->id,
                'nama_kelas' => $kelas->nama_kelas
            ],
            'data'    => $result
        ], Response::HTTP_OK);
    }

    public function update(Request $request, Siswa $siswa): JsonResponse
    {
        [$guru, $kelas, $taAktif] = $this->getIdentity();
        
        if (!$kelas) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $isMember = $siswa->riwayatKelas()
            ->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', $taAktif->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
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
            return response()->json(['success' => true, 'message' => 'Data berhasil diperbarui.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false, 
                'message' => 'Gagal update data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Siswa $siswa): JsonResponse
    {
        [$guru, $kelas, $taAktif] = $this->getIdentity();
        
        if (!$kelas) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $isMember = $siswa->riwayatKelas()
            ->where('kelas_id', $kelas->id)
            ->where('tahun_ajaran_id', $taAktif->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::beginTransaction();
            $user = $siswa->user;
            $siswa->riwayatKelas()->delete();
            $siswa->delete();
            if ($user) $user->delete();
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Siswa berhasil dihapus.'], Response::HTTP_OK);
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
                return response()->json(['success' => false, 'message' => 'Kelas tidak ditemukan.'], Response::HTTP_FORBIDDEN);
            }

            $query = Siswa::query();
            $query = $this->applyFilters($request, $query, $kelas->id, $taAktif->id);

            $nameParts = ['DATA_SISWA'];
            $nameParts[] = strtoupper(str_replace([' ', '-'], '_', $kelas->nama_kelas));
            
            if ($taAktif) {
                $nameParts[] = strtoupper(str_replace(['/', ' '], '_', $taAktif->nama));
                $nameParts[] = strtoupper($taAktif->semester);
            }
            
            $isActive = $request->get('is_active', 1);
            $nameParts[] = $isActive ? 'AKTIF' : 'TIDAK_AKTIF';
            
            $fileName = implode('_', $nameParts) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();

            return Excel::download(
                new SiswaExport(
                    $query, 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(), 
                    $kelas,
                    $request->all()
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