<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
<<<<<<< HEAD
use App\Http\Requests\StoreSiswaRequest;
use App\Http\Requests\UpdateSiswaRequest;
use App\Http\Resources\SiswaResource;
=======
use App\Models\Kelas;
use App\Models\User;
use App\Http\Requests\StoreSiswaRequest;
use App\Http\Requests\UpdateSiswaRequest;
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use App\Imports\SiswaImport;
use Maatwebsite\Excel\Facades\Excel;
>>>>>>> master
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
<<<<<<< HEAD
=======
use Illuminate\Support\Facades\Auth;
>>>>>>> master
use Illuminate\Support\Arr;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class SiswaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
<<<<<<< HEAD
        $this->middleware('role:Admin,Guru,Pembimbing')->only(['index', 'show']);
        $this->middleware('role:Admin')->except(['index', 'show']);
=======
        $this->middleware('role:Admin,Guru,Pembimbing')->only(['index', 'show', 'export']);
        $this->middleware('role:Admin')->except(['index', 'show', 'export']);
        $this->middleware('log.admin')->only(['store', 'update', 'destroy', 'import']);
    }

    private function applyFilters(Request $request, $query)
    {
        /** @var User $user */
        $user = User::with(['guruStaf.strukturJabatan.jabatan'])->find(Auth::id());
        $guruStaf = $user?->guruStaf;
        $namaJabatan = $guruStaf?->strukturJabatan?->jabatan?->nama_jabatan;

        if (!$this->hasFullAccess($request, $user, $namaJabatan)) {
            if ($guruStaf) {
                if ($namaJabatan === 'Ketua Jurusan') {
                    $query->whereHas('kelas', function ($q) use ($guruStaf) {
                        $q->where('jurusan_id', $guruStaf->jurusan_id);
                    });
                    $this->applyAdditionalFilters($request, $query);
                } else {
                    $kelasWali = Kelas::where('guru_staf_id', $guruStaf->id)->first();
                    $query->where('kelas_id', $kelasWali ? $kelasWali->id : 0);
                }
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $this->applyAdditionalFilters($request, $query);
        }

        return $query;
    }

    private function applyAdditionalFilters(Request $request, $query)
    {
        if ($request->filled('jurusan_id')) {
            $query->whereHas('kelas', fn($q) => $q->where('jurusan_id', $request->jurusan_id));
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhereHas('kelas', function ($qK) use ($search) {
                      $qK->where('nama_kelas', 'like', "%{$search}%");
                  });
            });
        }
    }

    private function hasFullAccess(Request $request, ?User $user, ?string $jabatan): bool
    {
        if (!$user) return false;
        if ($request->is('api/guru/*') || $request->is('guru/*')) return false;

        $isAdmin = $user->hasRole('Admin');
        $isKesiswaan = in_array($jabatan, ['Waka Kesiswaan', 'Kesiswaan']);

        return $isAdmin || $isKesiswaan;
>>>>>>> master
    }

    public function index(Request $request): JsonResponse
    {
        $query = Siswa::with(['user', 'kelas.jurusan', 'orangtua']);
<<<<<<< HEAD

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', '%' . $search . '%')
                  ->orWhereHas('kelas', function ($queryKelas) use ($search) {
                      $queryKelas->where('nama_kelas', 'like', '%' . $search . '%');
                  });
            });
        }

        $perPage = $request->filled('search') ? 10 : 20;
        $data    = $query->latest()->paginate($perPage);
=======
        $query = $this->applyFilters($request, $query);

        $perPage = $request->filled('search') ? 10 : 20;
        $data = $query->latest()->paginate($perPage);
>>>>>>> master

        return response()->json([
            'success' => true,
            'data'    => SiswaResource::collection($data),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ], Response::HTTP_OK);
    }

<<<<<<< HEAD
    public function store(StoreSiswaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $fillable  = (new Siswa())->getFillable();
        $data      = Arr::only($validated, $fillable);

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        try {
            $siswa = DB::transaction(function () use ($data) {
                return Siswa::create($data);
            });

            $siswa->load(['user', 'kelas.jurusan', 'orangtua']);

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil ditambahkan',
                'data'    => new SiswaResource($siswa)
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create siswa', ['payload' => $data, 'error' => $e->getMessage()]);
            if (!empty($data['foto'])) {
                Storage::disk('public')->delete($data['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan siswa',
                'errors'  => ['exception' => [$e->getMessage()]]
=======
    public function export(Request $request)
    {
        $query = Siswa::query();
        $query = $this->applyFilters($request, $query);

        return Excel::download(new SiswaExport($query), 'data_siswa_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            Excel::import(new SiswaImport, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diimport'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error("Import Siswa Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimport data: ' . $e->getMessage()
>>>>>>> master
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Siswa $siswa): JsonResponse
    {
<<<<<<< HEAD
=======
        $user = User::with(['guruStaf.strukturJabatan.jabatan'])->find(Auth::id());
        $guruStaf = $user?->guruStaf;
        $namaJabatan = $guruStaf?->strukturJabatan?->jabatan?->nama_jabatan;

        if (!$this->hasFullAccess(request(), $user, $namaJabatan)) {
            $allowed = false;
            if ($guruStaf) {
                if ($namaJabatan === 'Ketua Jurusan') {
                    $allowed = ($siswa->kelas?->jurusan_id === $guruStaf->jurusan_id);
                } else {
                    $kelasWali = Kelas::where('guru_staf_id', $guruStaf->id)->first();
                    $allowed = ($kelasWali && $siswa->kelas_id === $kelasWali->id);
                }
            }

            if (!$allowed) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
            }
        }

>>>>>>> master
        $siswa->load(['user', 'kelas.jurusan', 'orangtua']);
        return response()->json([
            'success' => true,
            'data'    => new SiswaResource($siswa)
        ], Response::HTTP_OK);
    }

<<<<<<< HEAD
    public function update(UpdateSiswaRequest $request, Siswa $siswa): JsonResponse
    {
        $validated = $request->validated();
        $fillable  = (new Siswa())->getFillable();
        $data      = Arr::only($validated, $fillable);
        $oldFoto   = $siswa->foto;
=======
    public function store(StoreSiswaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $data = Arr::only($validated, (new Siswa())->getFillable());
>>>>>>> master

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        try {
<<<<<<< HEAD
            DB::transaction(function () use ($siswa, $data) {
                $siswa->update($data);
            });

            if ($oldFoto && isset($data['foto'])) {
                Storage::disk('public')->delete($oldFoto);
            }

            $siswa->refresh()->load(['user', 'kelas.jurusan', 'orangtua']);

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diperbarui',
                'data'    => new SiswaResource($siswa)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update siswa', [
                'siswa_id' => (string) $siswa->id,
                'payload'  => $data,
                'error'    => $e->getMessage()
            ]);
            if (isset($data['foto'])) {
                Storage::disk('public')->delete($data['foto']);
            }
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui siswa',
                'errors'  => ['exception' => [$e->getMessage()]]
=======
            $siswa = DB::transaction(function() use ($data) {
                return Siswa::create($data);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil ditambahkan',
                'data'    => new SiswaResource($siswa->load(['user', 'kelas.jurusan', 'orangtua']))
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            if (isset($data['foto'])) Storage::disk('public')->delete($data['foto']);
            Log::error("Store Siswa Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan siswa'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateSiswaRequest $request, Siswa $siswa): JsonResponse
    {
        $validated = $request->validated();
        $data = Arr::only($validated, (new Siswa())->getFillable());
        $oldFoto = $siswa->foto;

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('siswa/foto', 'public');
        }

        try {
            DB::transaction(fn() => $siswa->update($data));

            if ($request->hasFile('foto') && $oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diperbarui',
                'data'    => new SiswaResource($siswa->fresh(['user', 'kelas.jurusan', 'orangtua']))
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            if (isset($data['foto'])) Storage::disk('public')->delete($data['foto']);
            Log::error("Update Siswa ID {$siswa->id} Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui siswa'
>>>>>>> master
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Siswa $siswa): JsonResponse
    {
        try {
<<<<<<< HEAD
            DB::transaction(function () use ($siswa) {
                $siswa->delete();
            });

            if ($siswa->foto) {
                Storage::disk('public')->delete($siswa->foto);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete siswa', ['siswa_id' => (string) $siswa->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus siswa',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
=======
            $fotoPath = $siswa->foto;
            DB::transaction(fn() => $siswa->delete());

            if ($fotoPath) {
                Storage::disk('public')->delete($fotoPath);
            }

            return response()->json([
                'success' => true, 
                'message' => 'Data siswa berhasil dihapus'
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error("Delete Siswa ID {$siswa->id} Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus siswa'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
>>>>>>> master
