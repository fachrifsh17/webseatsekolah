<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, User, TahunAjaran, SiswaKelas};
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Auth, Hash};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class SiswaController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'export']);
        $this->authorizeResource(Siswa::class, 'siswa');
    }

    private function getContext()
    {
        /** @var User $user */
        $user = Auth::user();
        $guruStaf = $user->guruStaf;
        $taAktif = TahunAjaran::where('is_active', true)->first();

        return [
            'jurusan' => ($guruStaf && $guruStaf->jurusan_id) ? $guruStaf->jurusan : null,
            'tahun_aktif' => $taAktif,
            'nama_jurusan' => $guruStaf?->jurusan?->nama_jurusan
        ];
    }

    public function index(Request $request): JsonResponse
    {
        ['jurusan' => $jurusan, 'tahun_aktif' => $taAktif] = $this->getContext();

        if (!$jurusan || !$taAktif) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak atau tahun ajaran aktif tidak ditemukan.'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $query = Siswa::with(['user', 'orangtua', 'riwayatKelas' => function($q) use ($taAktif) {
                $q->where('siswa_kelas.tahun_ajaran_id', $taAktif->id)
                  ->where('siswa_kelas.is_active', true)
                  ->with('kelas:id,nama_kelas');
            }]);

            $query->whereHas('riwayatKelas', function ($q) use ($jurusan, $taAktif) {
                $q->where('siswa_kelas.tahun_ajaran_id', $taAktif->id)
                  ->where('siswa_kelas.is_active', true)
                  ->whereHas('kelas', function($qK) use ($jurusan) {
                      $qK->where('jurusan_id', $jurusan->id);
                  });
            });

            if ($request->filled('kelas_id')) {
                $query->whereHas('riwayatKelas', function($q) use ($request) {
                    $q->where('kelas_id', $request->kelas_id)
                      ->where('siswa_kelas.is_active', true);
                });
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nisn', 'like', "%{$search}%");
                });
            }

            $perPage = (int) $request->get('per_page', 20);
            $paginated = $query->latest()->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'message' => 'Daftar siswa berhasil diambil.',
                'context' => [
                    'tahun_ajaran' => $taAktif->nama,
                    'semester'     => $taAktif->semester,
                    'jurusan'      => $jurusan->nama_jurusan
                ],
                'data' => SiswaResource::collection($paginated->items()),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                ],
                'links' => [
                    'first' => $paginated->url(1),
                    'last'  => $paginated->url($paginated->lastPage()),
                    'prev'  => $paginated->previousPageUrl(),
                    'next'  => $paginated->nextPageUrl(),
                ]
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Index Siswa Kajur Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data.'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        ['jurusan' => $jurusan, 'tahun_aktif' => $taAktif] = $this->getContext();
        
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nisn'         => 'required|string|unique:siswa,nisn',
            'kelas_id'     => 'required|exists:kelas,id',
            'email'        => 'required|email|unique:users,email',
            'jenis_kelamin'=> 'required|in:L,P',
        ]);

        $kelas = Kelas::where('id', $request->kelas_id)
            ->where('jurusan_id', $jurusan->id)
            ->where('tahun_ajaran_id', $taAktif->id)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            $user = User::create([
                'name'     => $request->nama_lengkap,
                'email'    => $request->email,
                'password' => Hash::make($request->nisn),
                'role'     => 'Siswa'
            ]);

            $siswa = Siswa::create(array_merge($request->except('kelas_id'), [
                'user_id' => $user->id,
                'is_active' => true 
            ]));

            SiswaKelas::create([
                'siswa_id'        => $siswa->id,
                'kelas_id'        => $kelas->id,
                'tahun_ajaran_id' => $taAktif->id,
                'is_active'       => true
            ]);

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil ditambahkan.',
                'context' => [
                    'tahun_ajaran' => $taAktif->nama,
                    'jurusan'      => $jurusan->nama_jurusan
                ],
                'data'    => new SiswaResource($siswa->load(['user', 'orangtua', 'riwayatKelas' => function($q) use ($taAktif) {
                    $q->where('tahun_ajaran_id', $taAktif->id)->where('is_active', true)->with('kelas:id,nama_kelas');
                }]))
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Store Siswa Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menambah data.'], 500);
        }
    }

    public function show(Siswa $siswa): JsonResponse
    {
        ['jurusan' => $jurusan, 'tahun_aktif' => $taAktif] = $this->getContext();

        $isAuthorized = $siswa->riwayatKelas()
            ->whereHas('kelas', function($qK) use ($jurusan) {
                $qK->where('jurusan_id', $jurusan->id);
            })->exists();

        if (!$isAuthorized) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        return response()->json([
            'success' => true,
            'context' => [
                'tahun_ajaran' => $taAktif?->nama,
                'jurusan'      => $jurusan->nama_jurusan
            ],
            'data'    => new SiswaResource($siswa->load(['user', 'orangtua', 'riwayatKelas' => function($q) use ($taAktif) {
                $q->where('siswa_kelas.tahun_ajaran_id', $taAktif->id)
                  ->where('siswa_kelas.is_active', true)
                  ->with('kelas:id,nama_kelas');
            }]))
        ], Response::HTTP_OK);
    }

    public function update(Request $request, Siswa $siswa): JsonResponse
    {
        ['jurusan' => $jurusan, 'tahun_aktif' => $taAktif] = $this->getContext();

        $isAuthorized = $siswa->riwayatKelas()
            ->whereHas('kelas', function($qK) use ($jurusan) {
                $qK->where('jurusan_id', $jurusan->id);
            })->exists();

        if (!$isAuthorized) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'nisn'         => 'sometimes|string|unique:siswa,nisn,' . $siswa->id,
            'kelas_id'     => 'sometimes|exists:kelas,id',
        ]);

        try {
            DB::beginTransaction();
            
            $siswa->update($request->except('kelas_id'));
            
            if ($request->filled('nama_lengkap')) {
                $siswa->user->update(['name' => $request->nama_lengkap]);
            }

            if ($request->filled('kelas_id')) {
                SiswaKelas::where('siswa_id', $siswa->id)
                    ->where('tahun_ajaran_id', $taAktif->id)
                    ->update(['is_active' => false]);

                SiswaKelas::updateOrCreate(
                    [
                        'siswa_id' => $siswa->id,
                        'kelas_id' => $request->kelas_id,
                        'tahun_ajaran_id' => $taAktif->id
                    ],
                    ['is_active' => true]
                );
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data berhasil diperbarui.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Update Siswa Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal update data.'], 500);
        }
    }

    public function destroy(Siswa $siswa): JsonResponse
    {
        ['jurusan' => $jurusan] = $this->getContext();

        $isAuthorized = $siswa->riwayatKelas()
            ->whereHas('kelas', function($qK) use ($jurusan) {
                $qK->where('jurusan_id', $jurusan->id);
            })->exists();

        if (!$isAuthorized) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        try {
            DB::beginTransaction();
            $user = $siswa->user;
            
            SiswaKelas::where('siswa_id', $siswa->id)->delete();
            
            $siswa->delete();
            if ($user) $user->delete();
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Delete Siswa Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            ['jurusan' => $jurusan, 'tahun_aktif' => $taAktif] = $this->getContext();

            if (!$jurusan || !$taAktif) abort(403);

            $query = Siswa::query()->with(['user', 'orangtua'])
                ->whereHas('riwayatKelas', function($q) use ($jurusan, $taAktif) {
                    $q->where('siswa_kelas.tahun_ajaran_id', $taAktif->id)
                      ->where('siswa_kelas.is_active', true)
                      ->whereHas('kelas', function($qK) use ($jurusan) {
                          $qK->where('jurusan_id', $jurusan->id);
                      });
                });

            if ($request->filled('kelas_id')) {
                $query->whereHas('riwayatKelas', function($q) use ($request) {
                    $q->where('kelas_id', $request->kelas_id);
                });
            }

            $nameParts = ['DATA_SISWA', strtoupper(str_replace(' ', '_', $jurusan->nama_jurusan))];
            $nameParts[] = strtoupper(str_replace(['/', ' '], '_', $taAktif->nama));
            $filename = implode('_', $nameParts) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();

            return Excel::download(
                new SiswaExport(
                    $query, 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(), 
                    $jurusan, 
                    $request->all()
                ), 
                $filename
            );

        } catch (Throwable $e) {
            Log::error('Export Siswa Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal ekspor data.'], 500);
        }
    }
}