<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, User};
use App\Http\Requests\{StoreSiswaRequest, UpdateSiswaRequest};
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use App\Imports\SiswaImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{DB, Storage, Log, Hash};
use Illuminate\Support\Arr;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SiswaController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'import']);

        $this->authorizeResource(Siswa::class, 'siswa');
    }
 
    private function applyFilters(Request $request, $query)
    {
        if ($request->filled('jurusan_id')) {
            $query->whereHas('kelas', fn($q) => $q->where('jurusan_id', $request->jurusan_id));
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        } else {
            $query->where('is_active', 1);
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

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Siswa::with(['user', 'kelas.jurusan', 'orangtua']);
        $query = $this->applyFilters($request, $query);

        $perPage = $request->get('per_page', $request->filled('search') ? 10 : 20);
        $data = $query->latest()->paginate($perPage);

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

    public function export(Request $request)
    {
        $this->authorize('viewAny', Siswa::class);

        $query = Siswa::query()->with(['kelas.jurusan', 'orangtua']);
        $query = $this->applyFilters($request, $query);

        $filename = 'data_siswa';
        $kelasData = null;

        if ($request->filled('kelas_id')) {
            $kelasData = Kelas::find($request->kelas_id);
            if ($kelasData) {
                $filename .= '_' . Str::slug($kelasData->nama_kelas);
            }
        } elseif ($request->filled('jurusan_id')) {
            $jurusan = DB::table('data_jurusan')->where('id', $request->jurusan_id)->first();
            if ($jurusan) {
                $filename .= '_' . Str::slug($jurusan->nama_jurusan);
            }
        }

        $is_active = $request->has('is_active') ? $request->is_active : 1;
        $filename .= $is_active ? '_aktif' : '_tidak_aktif';
        $filename .= '_' . now()->format('Ymd_His') . '.xlsx';

        $profil = DB::table('profil_sekolah')->first();
        $kontak = DB::table('data_kontak')->first();

        return Excel::download(
            new SiswaExport($query, $profil, $kontak, $kelasData, $request->all()), 
            $filename
        );
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', Siswa::class);

        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);

        try {
            $import = new SiswaImport;
            Excel::import($import, $request->file('file'));
            $conflicts = $import->getMessages();

            return response()->json([
                'success' => true,
                'message' => count($conflicts) > 0 ? 'Import selesai dengan catatan' : 'Data siswa berhasil diimport',
                'conflicts' => $conflicts
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error("Import Siswa Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimport data',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Siswa $siswa): JsonResponse
    {
        $siswa->load(['user', 'kelas.jurusan', 'orangtua']);
        return response()->json([
            'success' => true,
            'data'    => new SiswaResource($siswa)
        ], Response::HTTP_OK);
    }

    public function store(StoreSiswaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $data = Arr::only($validated, (new Siswa())->getFillable());

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('uploads/siswa/foto', 'public');
        }

        try {
            $siswa = DB::transaction(function() use ($data) {
                if (User::where('username', $data['nis'])->exists()) {
                    throw ValidationException::withMessages([
                        'nis' => ["NIS {$data['nis']} sudah terdaftar sebagai pengguna lain."]
                    ]);
                }

                $lastUser = User::where('id', 'like', 'U%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->lockForUpdate()
                    ->first();
                $lastUserId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
                $newUserId = 'U' . str_pad($lastUserId + 1, 3, '0', STR_PAD_LEFT);

                User::create([
                    'id'        => $newUserId,
                    'username'  => $data['nis'],
                    'password'  => Hash::make($data['nis']),
                    'is_active' => 1,
                ]);

                DB::table('user_roles')->insert([
                    'user_id'    => $newUserId,
                    'role_id'    => 'R003',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $lastSiswa = Siswa::where('id', 'like', 'S%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->lockForUpdate()
                    ->first();
                $lastSiswaId = $lastSiswa ? (int) substr($lastSiswa->id, 1) : 0;
                $newSiswaId = 'S' . str_pad($lastSiswaId + 1, 3, '0', STR_PAD_LEFT);

                $data['id'] = $newSiswaId;
                $data['user_id'] = $newUserId;
                $data['is_active'] = 1;

                return Siswa::create($data);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil ditambahkan',
                'data'    => new SiswaResource($siswa->load(['user', 'kelas.jurusan', 'orangtua']))
            ], Response::HTTP_CREATED);

        } catch (ValidationException $e) {
            if (isset($data['foto'])) Storage::disk('public')->delete($data['foto']);
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
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
            $data['foto'] = $request->file('foto')->store('uploads/siswa/foto', 'public');
        }

        try {
            DB::transaction(function() use ($siswa, $data) {
                if (isset($data['nis']) && $siswa->user_id) {
                    $isTaken = User::where('username', $data['nis'])
                                   ->where('id', '!=', $siswa->user_id)
                                   ->exists();
                    if ($isTaken) {
                        throw ValidationException::withMessages([
                            'nis' => ["NIS {$data['nis']} sudah digunakan oleh pengguna lain."]
                        ]);
                    }
                }

                $siswa->update($data);

                if ($siswa->user_id) {
                    $userData = [];
                    if (isset($data['nis'])) $userData['username'] = $data['nis'];
                    if (isset($data['is_active'])) $userData['is_active'] = $data['is_active'];
                    
                    if (!empty($userData)) {
                        User::where('id', $siswa->user_id)->update($userData);
                    }
                }
            });

            if ($request->hasFile('foto') && $oldFoto) {
                Storage::disk('public')->delete($oldFoto);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diperbarui',
                'data'    => new SiswaResource($siswa->fresh(['user', 'kelas.jurusan', 'orangtua']))
            ], Response::HTTP_OK);

        } catch (ValidationException $e) {
            if ($request->hasFile('foto') && isset($data['foto'])) Storage::disk('public')->delete($data['foto']);
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            if ($request->hasFile('foto') && isset($data['foto'])) Storage::disk('public')->delete($data['foto']);
            Log::error("Update Siswa Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui siswa'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Siswa $siswa): JsonResponse
    {
        try {
            $fotoPath = $siswa->foto;
            $userId = $siswa->user_id;

            DB::transaction(function() use ($siswa, $userId) {
                $siswa->delete();
                if ($userId) {
                    User::where('id', $userId)->delete();
                }
            });

            if ($fotoPath) {
                Storage::disk('public')->delete($fotoPath);
            }

            return response()->json([
                'success' => true, 
                'message' => 'Data siswa berhasil dihapus'
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error("Delete Siswa Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus siswa'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}