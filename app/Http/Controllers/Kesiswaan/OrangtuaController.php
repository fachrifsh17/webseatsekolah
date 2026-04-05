<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\{Orangtua, Siswa, User, Kelas, Jurusan, Semester};
use App\Http\Requests\{StoreOrangtuaRequest, UpdateOrangtuaRequest};
use App\Http\Resources\OrangtuaResource;
use App\Exports\OrangtuaExport;
use App\Imports\OrangtuaImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{DB, Log, Hash};
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class OrangtuaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'import', 'bulkDelete']);
        $this->authorizeResource(Orangtua::class, 'orangtua');
    }

 private function applyFilters(Request $request, $query)
{
    $query->distinct();
    $semesterAktif = DB::table('semesters')->where('is_active', 1)->first();
    $semesterId = $request->query('semester_id', $semesterAktif?->id);
    $isActiveFilter = $request->query('is_active', '1');

    // Menentukan apakah ada filter akademik yang sedang aktif
    $hasAcademicFilter = $request->filled('tingkatan_id') || 
                         $request->filled('jurusan_id') || 
                         $request->filled('kelas_id');

    // Filter Utama: Mengunci relasi Orang Tua -> Anak -> Riwayat Kelas
    $query->whereHas('anak', function ($qa) use ($semesterId, $request, $hasAcademicFilter, $isActiveFilter) {
        $qa->whereHas('riwayatKelas', function ($q) use ($semesterId, $request, $hasAcademicFilter, $isActiveFilter) {
            
            // 1. Logika Kritis: Jika memfilter kelas/jurusan/tingkat, WAJIB is_active = 1
            // atau jika filter status orang tua adalah 'Aktif'
            if ($hasAcademicFilter || $isActiveFilter == '1') {
                $q->where('is_active', 1);
                if ($semesterId) {
                    $q->where('semester_id', $semesterId);
                }
            }

            // 2. Terapkan filter akademik secara spesifik
            if ($request->filled('tingkatan_id')) {
                $q->whereHas('kelas', function($qk) use ($request) {
                    $qk->where('tingkatan_id', $request->tingkatan_id);
                });
            }

            if ($request->filled('jurusan_id')) {
                $q->whereHas('kelas', function($qk) use ($request) {
                    $qk->where('jurusan_id', $request->jurusan_id);
                });
            }

            if ($request->filled('kelas_id')) {
                $q->where('kelas_id', $request->kelas_id);
            }
        });
    });

    // Filter Pencarian (Nama/Telepon/NIS)
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

    // Filter Status Orang Tua (Aktif/Non-Aktif/All)
    if ($isActiveFilter !== 'all' && $isActiveFilter !== null) {
        $query->where('is_active', $isActiveFilter);
    }

    return $query;
}
public function index(Request $request): JsonResponse
{
    try {
        $semesterAktif = DB::table('semesters')->where('is_active', 1)->first();
        $semesterId = $request->query('semester_id', $semesterAktif?->id);

        $query = Orangtua::with(['user', 'anak' => function($q) {
            $q->with(['riwayatKelas' => function($qsk) {
                $qsk->orderBy('id', 'desc')->with('kelas.jurusan');
            }]);
        }]);

        $query = $this->applyFilters($request, $query);
        $perPage = $request->query('per_page', 20);
        $orangtua = $query->latest()->paginate($perPage);
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
                'semester_fokus' => $semesterId
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
                    'current_role' => 'Orangtua',
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
                            ->whereHas('riwayatKelas', function($q) {
                                $q->where('is_active', 1)
                                  ->whereHas('semester', fn($sem) => $sem->where('is_active', 1));
                            })
                            ->first();

                        if (!$siswa) {
                            throw new \Exception("Siswa dengan NIS {$item['nis']} tidak ditemukan dalam kelas aktif periode ini.");
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
                'data'    => new OrangtuaResource($orangtua->load(['user','anak.riwayatKelas' => fn($q) => $q->where('is_active', 1)->with('kelas.jurusan')]))
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

                $isReactivating = isset($validated['is_active']) && $orangtua->is_active == 0 && $validated['is_active'] == 1;

                if ($orangtua->user) {
                    $userData = [];
                    if (isset($validated['telepon'])) {
                        $userData['username'] = $validated['telepon'];
                        $userData['password'] = Hash::make($validated['telepon']);
                    }
                    if (isset($validated['is_active'])) {
                        $userData['is_active'] = $validated['is_active'];
                        if ($isReactivating && !isset($validated['telepon'])) {
                            $userData['password'] = Hash::make($orangtua->telepon);
                        }
                    }
                    if (!empty($userData)) {
                        $orangtua->user->update($userData);
                    }
                }

                if (!isset($validated['is_active'])) {
                    $validated['is_active'] = $orangtua->is_active;
                }

                $orangtua->update($validated);

                if (isset($anakList)) {
                    $syncData = [];
                    foreach ($anakList as $item) {
                        $siswa = Siswa::where('nis', $item['nis'])
                            ->whereHas('riwayatKelas', function($q) {
                                $q->where('is_active', 1)
                                  ->whereHas('semester', fn($sem) => $sem->where('is_active', 1));
                            })
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
                'data'    => new OrangtuaResource($orangtua->load(['user','anak.riwayatKelas' => fn($q) => $q->where('is_active', 1)->with('kelas.jurusan')]))
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
            'data'    => new OrangtuaResource($orangtua->load(['user','anak.riwayatKelas' => fn($q) => $q->where('is_active', 1)->with('kelas.jurusan')]))
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

    public function bulkDelete(Request $request): JsonResponse
{
    $request->validate([
        'ids' => 'required|array',
        'ids.*' => 'exists:orangtua,id'
    ]);

    try {
        DB::transaction(function () use ($request) {
            $orangtuas = Orangtua::with(['user', 'anak'])->whereIn('id', $request->ids)->get();

            /** @var \App\Models\Orangtua $ortua */
            foreach ($orangtuas as $ortua) {
                $user = $ortua->user;

                $ortua->anak()->detach();
                $ortua->delete();

                if ($user) {
                    $user->roles()->detach();
                    $user->delete();
                }
            }
        });

        return response()->json([
            'success' => true, 
            'message' => 'Beberapa data orang tua berhasil dihapus'
        ], Response::HTTP_OK);

    } catch (Throwable $e) {
        Log::error('Bulk Delete Orangtua Error: ' . $e->getMessage());
        return response()->json([
            'success' => false, 
            'message' => 'Gagal menghapus data masal: ' . $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

   public function export(Request $request)
    {
        try {
            $this->authorize('viewAny', Orangtua::class);

            $semesterAktif = DB::table('semesters')->where('is_active', 1)->first();
            $semesterId = $request->query('semester_id', $semesterAktif?->id);

            $query = Orangtua::query()->with(['anak.riwayatKelas' => function($q) use ($semesterId) {
                $q->where('is_active', 1);
                if ($semesterId) {
                    $q->where('semester_id', $semesterId);
                }
                $q->with('kelas.jurusan');
            }]);

            $query = $this->applyFilters($request, $query);
            
            $sem = DB::table('semesters')
                ->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                ->where('semesters.id', $semesterId)
                ->select('semesters.nama as nama_semester', 'semesters.is_active', 'tahun_ajaran.nama as nama_tahun_ajaran')
                ->first();

            $kelasData = $request->filled('kelas_id') ? Kelas::find($request->kelas_id) : null;
            $jurusanData = $request->filled('jurusan_id') ? Jurusan::find($request->jurusan_id) : null;
            
            $filenameParts = ['DATA_ORANGTUA'];
            
            if ($kelasData) { 
                $filenameParts[] = strtoupper(str_replace([' ', '-'], '_', $kelasData->nama_kelas)); 
            } elseif ($jurusanData) { 
                $filenameParts[] = strtoupper(str_replace([' ', '-'], '_', $jurusanData->nama_jurusan)); 
            }

            if ($sem) {
                $namaTA = strtoupper(str_replace(['/', '-'], '_', $sem->nama_tahun_ajaran));
                $filenameParts[] = $namaTA;
                $namaSem = strtoupper(str_replace(['/', '-'], '_', $sem->nama_semester));
                $filenameParts[] = $namaSem;
                
                if (!$sem->is_active) {
                    $filenameParts[] = 'HISTORY';
                }
            }

            $isActiveFilter = $request->query('is_active', '1');
            if ($isActiveFilter === '0') {
                $filenameParts[] = 'NON_AKTIF';
            } elseif ($isActiveFilter === '1') {
                if ($sem && $sem->is_active) {
                    $filenameParts[] = 'AKTIF';
                }
            } else {
                $filenameParts[] = 'SEMUA';
            }

            $fileName = implode('_', $filenameParts) . '.xlsx';
            
            if (ob_get_contents()) ob_end_clean();
            return Excel::download(new OrangtuaExport($query, DB::table('profil_sekolah')->first(), DB::table('data_kontak')->first(), $kelasData, $request->all(), $jurusanData), $fileName);
            
        } catch (Throwable $e) {
            Log::error('Export Orangtua Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Gagal mengunduh data: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function importPreview(Request $request): JsonResponse
    {
        $this->authorize('create', Orangtua::class);
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            $rows = Excel::toArray(new \stdClass(), $request->file('file'))[0];
            $preview = [];
            $activeSemesterId = Semester::where('is_active', true)->value('id');
            $tempTelephones = [];

            foreach (array_slice($rows, 1) as $index => $row) {
                $raw = array_combine($rows[0], $row);
                $errors = [];
                $warnings = [];
                $line = $index + 2;

                if (empty($raw['nama_lengkap'])) $errors[] = "Nama lengkap wajib diisi.";
                
                if (empty($raw['telepon'])) {
                    $errors[] = "Telepon wajib diisi.";
                } else {
                    $telepon = preg_replace('/[^0-9]/', '', (string) $raw['telepon']);

                    if (in_array($telepon, $tempTelephones)) {
                        $errors[] = "Nomor telepon duplikat dengan baris sebelumnya di file ini.";
                    }
                    $tempTelephones[] = $telepon;

                    $existingOrangtua = Orangtua::where('telepon', $telepon)->first();
                    if ($existingOrangtua) {
                        if ($existingOrangtua->is_active) {
                            $errors[] = "Nomor telepon sudah digunakan oleh orang tua aktif ({$existingOrangtua->nama_lengkap}).";
                        } else {
                            $warnings[] = "Nomor telepon terdaftar sebagai data non-aktif. Akan diaktifkan kembali jika diimport.";
                        }
                    }
                }

                if (!empty($raw['nis_anak'])) {
                    $nisList = explode(',', $raw['nis_anak']);
                    foreach ($nisList as $nis) {
                        $nisClean = trim($nis);
                        $siswa = Siswa::where('nis', $nisClean)
                            ->where('is_active', 1)
                            ->whereHas('riwayatKelas', fn($q) => $q->where('semester_id', $activeSemesterId))
                            ->first();
                        
                        if (!$siswa) {
                            $errors[] = "Siswa NIS {$nisClean} tidak ditemukan/aktif di semester ini.";
                        } else {
                            if ($siswa->orangtua()->count() >= 2) {
                                $errors[] = "Siswa {$nisClean} sudah memiliki maksimal 2 orang tua.";
                            }
                        }
                    }
                }

                $preview[] = [
                    'line' => $line,
                    'data' => $raw,
                    'errors' => $errors,
                    'warnings' => $warnings,
                    'is_valid' => empty($errors)
                ];
            }

            return response()->json(['success' => true, 'data' => $preview]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
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