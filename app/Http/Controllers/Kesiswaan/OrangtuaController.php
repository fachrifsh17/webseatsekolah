<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\{Orangtua, Siswa, User, Kelas};
use App\Http\Requests\{StoreOrangtuaRequest, UpdateOrangtuaRequest};
use App\Http\Resources\OrangtuaResource;
use App\Exports\OrangtuaExport;
use App\Imports\OrangtuaImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Log, Hash};
use Illuminate\Support\Str;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class OrangtuaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'import']);

        $this->authorizeResource(Orangtua::class, 'orangtua');
    }

    private function applyFilters(Request $request, $query)
    {
        $query->distinct();

        $tahunAjaranAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
        $tahunAjaranId = $request->query('tahun_ajaran_id', $tahunAjaranAktif?->id);

        $query->whereHas('anak.kelas', function ($q) use ($tahunAjaranId) {
            if ($tahunAjaranId) {
                $q->where('tahun_ajaran_id', $tahunAjaranId);
            }
        });

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('telepon', 'like', "%{$search}%")
                  ->orWhereHas('anak', function($qa) use ($search) {
                      $qa->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nis', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('jurusan_id')) {
            $query->whereHas('anak.kelas', function ($q) use ($request) {
                $q->where('jurusan_id', $request->jurusan_id);
            });
        }

        if ($request->filled('kelas_id')) {
            $query->whereHas('anak', function ($q) use ($request) {
                $q->where('kelas_id', $request->kelas_id);
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $tahunAjaranAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
            $tahunAjaranId = $request->query('tahun_ajaran_id', $tahunAjaranAktif?->id);

            $query = Orangtua::with(['user', 'anak' => function($q) use ($tahunAjaranId) {
                $q->whereHas('kelas', function($qk) use ($tahunAjaranId) {
                    $qk->where('tahun_ajaran_id', $tahunAjaranId);
                })->with('kelas.jurusan');
            }]);

            $query = $this->applyFilters($request, $query);
            
            $perPage = $request->query('per_page', 20);
            $orangtua = $query->latest()->paginate($perPage);
            
            // Mengambil metadata pagination secara lengkap
            $paginationData = $orangtua->toArray();

            return response()->json([
                'success' => true,
                'data'    => OrangtuaResource::collection($orangtua),
                'meta'    => [
                    'current_page'  => $orangtua->currentPage(),
                    'last_page'     => $orangtua->lastPage(),
                    'per_page'      => $orangtua->perPage(),
                    'total'         => $orangtua->total(),
                    'from'          => $orangtua->firstItem(),
                    'to'            => $orangtua->lastItem(),
                    'next_page_url' => $orangtua->nextPageUrl(),
                    'prev_page_url' => $orangtua->previousPageUrl(),
                    'path'          => $paginationData['path'],
                    'links'         => $paginationData['links'],
                    'tahun_ajaran_fokus' => $tahunAjaranId
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Fetch Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data orang tua'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreOrangtuaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        try {
            $orangtua = DB::transaction(function () use ($validated) {
                $lastUser = User::where('id', 'like', 'U%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->lockForUpdate()->first();
                $newUserId = 'U' . str_pad(($lastUser ? (int) substr($lastUser->id, 1) : 0) + 1, 3, '0', STR_PAD_LEFT);

                User::create([
                    'id' => $newUserId,
                    'username' => $validated['telepon'],
                    'password' => Hash::make($validated['telepon']),
                    'is_active' => 1,
                ]);

                DB::table('user_roles')->insert([
                    'user_id' => $newUserId,
                    'role_id' => 'R004',
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                $anakList = $validated['anak'] ?? [];
                unset($validated['anak']);

                $orangtua = Orangtua::create(array_merge($validated, ['user_id' => $newUserId]));

                if (!empty($anakList)) {
                    $syncData = [];
                    foreach ($anakList as $item) {
                        $siswa = Siswa::where('nis', $item['nis'])
                            ->whereHas('kelas.tahunAjaran', fn($q) => $q->where('is_active', 1))
                            ->first();

                        if (!$siswa) {
                            throw new \Exception("Siswa dengan NIS {$item['nis']} tidak ditemukan di Tahun Ajaran Aktif.");
                        }

                        if ($siswa->orangtua()->count() >= 2) {
                            throw new \Exception("Siswa {$siswa->nama_lengkap} sudah memiliki maksimal 2 orang tua.");
                        }
                        
                        $syncData[$siswa->id] = ['hubungan' => $item['hubungan'] ?? null];
                    }
                    $orangtua->anak()->sync($syncData);
                }
                return $orangtua;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data orang tua berhasil dibuat',
                'data'    => new OrangtuaResource($orangtua->load(['user','anak.kelas.jurusan']))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Create Orangtua Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function update(UpdateOrangtuaRequest $request, Orangtua $orangtua): JsonResponse
    {
        $validated = $request->validated();
        try {
            DB::transaction(function () use ($validated, $orangtua) {
                $anakList = $validated['anak'] ?? [];
                unset($validated['anak']);

                if (isset($validated['telepon']) && $orangtua->user) {
                    $orangtua->user->update([
                        'username' => $validated['telepon']
                    ]);
                }

                $orangtua->update($validated);

                if (isset($anakList)) {
                    $syncData = [];
                    foreach ($anakList as $item) {
                        $siswa = Siswa::where('nis', $item['nis'])
                            ->whereHas('kelas.tahunAjaran', fn($q) => $q->where('is_active', 1))
                            ->first();

                        if (!$siswa) {
                            throw new \Exception("Siswa dengan NIS {$item['nis']} tidak ditemukan atau tidak aktif di periode ini.");
                        }

                        $count = $siswa->orangtua()->where('orangtua.id', '!=', $orangtua->id)->count();
                        if ($count >= 2) {
                            throw new \Exception("Siswa {$siswa->nama_lengkap} sudah memiliki maksimal 2 orang tua.");
                        }
                        $syncData[$siswa->id] = ['hubungan' => $item['hubungan'] ?? null];
                    }
                    $orangtua->anak()->sync($syncData);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil diperbarui',
                'data'    => new OrangtuaResource($orangtua->load(['user','anak.kelas.jurusan']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Orangtua Error', ['id' => $orangtua->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function show(Orangtua $orangtua): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new OrangtuaResource($orangtua->load(['user','anak.kelas.jurusan']))
        ], Response::HTTP_OK);
    }

    public function destroy(Orangtua $orangtua): JsonResponse
    {
        try {
            DB::transaction(function () use ($orangtua) {
                $user = $orangtua->user;
                $orangtua->anak()->detach();
                $orangtua->delete();
                if ($user) {
                    $user->roles()->detach();
                    $user->delete();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Data orang tua berhasil dihapus'
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Orangtua Error', ['id' => $orangtua->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        try {
            $this->authorize('viewAny', Orangtua::class);
            
            $query = Orangtua::query()->with(['anak.kelas.jurusan']);
            $query = $this->applyFilters($request, $query);
            
            $tahunAjaranAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
            $tahunAjaranId = $request->query('tahun_ajaran_id', $tahunAjaranAktif?->id);
            $ta = DB::table('tahun_ajaran')->where('id', $tahunAjaranId)->first();
            
            $filenameParts = ['DATA_ORANGTUA'];

            if ($request->filled('kelas_id')) {
                $kelas = DB::table('kelas')->where('id', $request->kelas_id)->first();
                if ($kelas) {
                    $filenameParts[] = strtoupper(str_replace([' ', '-'], '_', $kelas->nama_kelas));
                }
            } elseif ($request->filled('jurusan_id')) {
                $jurusan = DB::table('jurusan')->where('id', $request->jurusan_id)->first();
                if ($jurusan) {
                    $filenameParts[] = strtoupper(str_replace([' ', '-'], '_', $jurusan->nama_jurusan));
                }
            }

            if ($ta) {
                $namaTA = strtoupper(str_replace(['/', '-'], '_', $ta->nama));
                $semester = strtoupper($ta->semester ?? 'SEM'); 
                $filenameParts[] = $namaTA;
                $filenameParts[] = $semester;
                $filenameParts[] = ($ta->is_active) ? 'AKTIF' : 'HISTORY';
            }

            $fileName = implode('_', $filenameParts) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();

            return Excel::download(
                new OrangtuaExport(
                    $query, 
                    DB::table('profil_sekolah')->first(), 
                    DB::table('data_kontak')->first(), 
                    null, 
                    $request->all()
                ), 
                $fileName
            );
        } catch (Throwable $e) {
            Log::error('Export Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengunduh data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', Orangtua::class);
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);
        try {
            $import = new OrangtuaImport;
            Excel::import($import, $request->file('file'));
            return response()->json([
                'success' => true, 
                'message' => count($import->getMessages()) > 0 ? 'Import selesai dengan catatan.' : 'Berhasil diimport.', 
                'conflicts' => $import->getMessages()
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Orangtua Error: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal mengimpor data: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}