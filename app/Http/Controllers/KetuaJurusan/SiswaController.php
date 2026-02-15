<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, User};
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Auth, Hash};
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
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'export']);
        $this->authorizeResource(Siswa::class, 'siswa');
    }

    private function getJurusanKetua()
    {
        /** @var User $user */
        $user = Auth::user();
        $guruStaf = $user->guruStaf;

        return ($guruStaf && $guruStaf->jurusan_id) ? $guruStaf->jurusan : null;
    }

    public function index(Request $request): JsonResponse
    {
        $jurusan = $this->getJurusanKetua();

        if (!$jurusan) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak.'
            ], Response::HTTP_FORBIDDEN);
        }

        $query = Siswa::with(['user', 'kelas.jurusan', 'orangtua', 'kelas.tahunAjaran']);

        // Proteksi Jurusan
        $query->whereHas('kelas', function ($q) use ($jurusan) {
            $q->where('jurusan_id', $jurusan->id);
        });

        // Filter berdasarkan kelas yang diklik
        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        $data = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar siswa berhasil diambil.',
            'data'    => SiswaResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        $jurusan = $this->getJurusanKetua();
        
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nisn'         => 'required|string|unique:siswa,nisn',
            'kelas_id'     => 'required|exists:kelas,id',
            'email'        => 'required|email|unique:users,email',
            'jenis_kelamin'=> 'required|in:L,P',
        ]);

        // Pastikan kelas tersebut memang milik jurusannya
        $kelas = Kelas::where('id', $request->kelas_id)
            ->where('jurusan_id', $jurusan->id)
            ->firstOrFail();

        try {
            DB::beginTransaction();

            $user = User::create([
                'name'     => $request->nama_lengkap,
                'email'    => $request->email,
                'password' => Hash::make($request->nisn),
                'role'     => 'Siswa'
            ]);

            $siswa = Siswa::create(array_merge($request->all(), ['user_id' => $user->id]));

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil ditambahkan.',
                'data'    => new SiswaResource($siswa->load('user', 'kelas'))
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Store Siswa Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menambah data.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Siswa $siswa): JsonResponse
    {
        $jurusan = $this->getJurusanKetua();

        if (!$jurusan || $siswa->kelas->jurusan_id !== $jurusan->id) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak.'
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data'    => new SiswaResource($siswa->load(['user', 'kelas.jurusan', 'orangtua']))
        ], Response::HTTP_OK);
    }

    public function update(Request $request, Siswa $siswa): JsonResponse
    {
        $jurusan = $this->getJurusanKetua();
        if (!$jurusan || $siswa->kelas->jurusan_id !== $jurusan->id) {
            return response()->json([
                'success' => false, 
                'message' => 'Akses ditolak.'
            ], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'nisn'         => 'sometimes|string|unique:siswa,nisn,' . $siswa->id,
            'kelas_id'     => 'sometimes|exists:kelas,id',
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
                'message' => 'Data berhasil diperbarui.'
            ], Response::HTTP_OK);
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
        $jurusan = $this->getJurusanKetua();
        if (!$jurusan || $siswa->kelas->jurusan_id !== $jurusan->id) {
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
                'message' => 'Data berhasil dihapus.'
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
        $jurusan = $this->getJurusanKetua();
        if (!$jurusan) abort(Response::HTTP_FORBIDDEN);

        $query = Siswa::whereHas('kelas', fn($q) => $q->where('jurusan_id', $jurusan->id));
        
        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        return Excel::download(
            new SiswaExport($query, DB::table('profil_sekolah')->first(), DB::table('data_kontak')->first(), $jurusan), 
            'Data_Siswa_' . Str::slug($jurusan->nama_jurusan) . '.xlsx'
        );
    }
}