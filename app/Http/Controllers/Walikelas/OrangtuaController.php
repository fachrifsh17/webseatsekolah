<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Orangtua, Kelas, User, Siswa, TahunAjaran};
use App\Http\Resources\OrangtuaResource;
use App\Exports\OrangtuaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Log, Hash};
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Throwable;

class OrangtuaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Guru');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'export']);
    }

    private function getKelasPerwalian()
    {
        $guruId = Auth::user()->guruStaf?->id;
        if (!$guruId) return null;

        $taAktif = TahunAjaran::where('is_active', true)->first();
        if (!$taAktif) return null;

        return Kelas::with(['jurusan', 'tahunAjaran'])
            ->where('wali_kelas_id', $guruId)
            ->where('tahun_ajaran_id', $taAktif->id)
            ->where('is_active', 1)
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
                    $q->where('kelas_id', $kelas->id);
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
            $paginatedData = $query->latest()->paginate($perPage);
            
            // Konversi data paginasi ke array untuk mengambil metadata lengkap
            $paginationArray = $paginatedData->toArray();

            return response()->json([
                'success' => true,
                'message' => "Daftar orang tua siswa kelas {$kelas->nama_kelas} berhasil diambil.",
                'kelas'   => [
                    'id'      => $kelas->id,
                    'nama'    => $kelas->nama_kelas,
                    'jurusan' => $kelas->jurusan?->nama_jurusan
                ],
                'data'    => OrangtuaResource::collection($paginatedData),
                'meta'    => [
                    'current_page'  => $paginationArray['current_page'],
                    'last_page'     => $paginationArray['last_page'],
                    'per_page'      => $paginationArray['per_page'],
                    'total'         => $paginationArray['total'],
                    'from'          => $paginationArray['from'],
                    'to'            => $paginationArray['to'],
                    'path'          => $paginationArray['path'],
                    'next_page_url' => $paginationArray['next_page_url'],
                    'prev_page_url' => $paginationArray['prev_page_url'],
                    'links'         => array_map(function ($link) {
                        return [
                            'url'    => $link['url'],
                            'label'  => $link['label'],
                            'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
                            'active' => $link['active'],
                        ];
                    }, $paginationArray['links']),
                ]
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Walikelas Orangtua Index Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data orang tua.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $kelas = $this->getKelasPerwalian();
        if (!$kelas) return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);

        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'no_telp'      => 'required|string|max:15',
            'siswa_id'     => 'required|exists:siswa,id',
        ]);

        $siswa = Siswa::where('id', $request->siswa_id)->where('kelas_id', $kelas->id)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan di kelas Anda.'], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::beginTransaction();

            $user = User::create([
                'name'     => $request->nama_lengkap,
                'email'    => $request->email,
                'password' => Hash::make('ortu1234'),
                'role'     => 'Orangtua'
            ]);

            $ortu = Orangtua::create([
                'user_id'      => $user->id,
                'nama_lengkap' => $request->nama_lengkap,
                'no_telp'      => $request->no_telp,
                'pekerjaan'    => $request->pekerjaan,
                'alamat'       => $request->alamat,
                'is_active'    => 1
            ]);

            $siswa->update(['orangtua_id' => $ortu->id]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data orang tua berhasil ditambahkan.',
                'data'    => new OrangtuaResource($ortu->load('user', 'anak'))
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Store Orangtua Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal menambah data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Orangtua $orangtua): JsonResponse
    {
        $kelas = $this->getKelasPerwalian();
        if (!$kelas || !$orangtua->anak()->where('kelas_id', $kelas->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'kelas'   => [
                'id'   => $kelas->id,
                'nama' => $kelas->nama_kelas,
            ],
            'data'    => new OrangtuaResource($orangtua->load(['user', 'anak' => function($q) use ($kelas) {
                $q->where('kelas_id', $kelas->id);
            }]))
        ], Response::HTTP_OK);
    }

    public function update(Request $request, Orangtua $orangtua): JsonResponse
    {
        $kelas = $this->getKelasPerwalian();
        if (!$kelas || !$orangtua->anak()->where('kelas_id', $kelas->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'no_telp'      => 'sometimes|string|max:15',
        ]);

        try {
            DB::beginTransaction();
            
            $updateData = $request->only(['nama_lengkap', 'no_telp', 'pekerjaan', 'alamat', 'is_active']);
            $orangtua->update($updateData);

            if ($request->filled('nama_lengkap')) {
                $orangtua->user->update(['name' => $request->nama_lengkap]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data orang tua berhasil diperbarui.',
                'data'    => new OrangtuaResource($orangtua->load('user'))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Orangtua $orangtua): JsonResponse
    {
        $kelas = $this->getKelasPerwalian();
        if (!$kelas || !$orangtua->anak()->where('kelas_id', $kelas->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::beginTransaction();
            $user = $orangtua->user;
            
            Siswa::where('orangtua_id', $orangtua->id)
                 ->where('kelas_id', $kelas->id)
                 ->update(['orangtua_id' => null]);

            $orangtua->delete();
            if ($user) $user->delete();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data orang tua berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $kelas = $this->getKelasPerwalian();
            if (!$kelas) {
                return response()->json(['success' => false, 'message' => 'Gagal ekspor: Kelas tidak ditemukan.'], Response::HTTP_FORBIDDEN);
            }

            $query = Orangtua::query()
                ->with(['anak' => function($q) use ($kelas) {
                    $q->where('kelas_id', $kelas->id);
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

            $nameParts = ['DATA_ORANGTUA'];
            $nameParts[] = strtoupper(str_replace(' ', '_', $kelas->nama_kelas));

            if ($kelas->tahunAjaran) {
                $taClean = str_replace(['/', ' '], '_', $kelas->tahunAjaran->nama);
                $nameParts[] = strtoupper($taClean);
                $nameParts[] = strtoupper($kelas->tahunAjaran->semester);
            }

            $nameParts[] = 'AKTIF';
            $filename = implode('_', $nameParts) . '.xlsx';
            
            if (ob_get_contents()) ob_end_clean();

            return Excel::download(
                new OrangtuaExport(
                    $query, 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(), 
                    $kelas, 
                    $request->all(), 
                    $kelas->jurusan
                ), 
                $filename
            );

        } catch (Throwable $e) {
            Log::error('Export Orangtua Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengekspor data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}