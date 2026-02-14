<?php

namespace App\Http\Controllers\Walikelas;

use App\Http\Controllers\Controller;
use App\Models\{Orangtua, Kelas, User, Siswa};
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
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'export']);
    }

    private function getKelasPerwalian()
    {
        $guruStafId = Auth::user()->guruStaf?->id;

        return Kelas::with('jurusan')
            ->where('wali_kelas_id', $guruStafId)
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
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan di kelas perwalian Anda.'], Response::HTTP_FORBIDDEN);
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
            ]);

            $siswa->update(['orangtua_id' => $ortu->id]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data orang tua berhasil ditambahkan dan ditautkan ke siswa.',
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
        $isWali = $orangtua->anak()->where('kelas_id', $kelas?->id)->exists();

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

    public function update(Request $request, Orangtua $orangtua): JsonResponse
    {
        $kelas = $this->getKelasPerwalian();
        $isWali = $orangtua->anak()->where('kelas_id', $kelas?->id)->exists();

        if (!$isWali) return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);

        $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'no_telp'      => 'sometimes|string|max:15',
        ]);

        try {
            DB::beginTransaction();
            $orangtua->update($request->all());
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
        $isWali = $orangtua->anak()->where('kelas_id', $kelas?->id)->exists();

        if (!$isWali) return response()->json(['success' => false, 'message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);

        try {
            DB::beginTransaction();
            $user = $orangtua->user;
            Siswa::where('orangtua_id', $orangtua->id)->update(['orangtua_id' => null]);
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
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal ekspor: Kelas perwalian tidak ditemukan.'
                ], Response::HTTP_FORBIDDEN);
            }

            $jurusanData = $kelas->jurusan;

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
                new OrangtuaExport(
                    $query, 
                    $profil, 
                    $kontak, 
                    $kelas, 
                    $request->all(), 
                    $jurusanData
                ), 
                $filename
            );

        } catch (Throwable $e) {
            Log::error('Gagal ekspor data orang tua: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengekspor data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}