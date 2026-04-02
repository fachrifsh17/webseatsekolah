<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruStaf;
use App\Models\Jurusan;
use App\Models\User;
use App\Models\DataKontak;
use App\Models\ProfilSekolah;
use App\Http\Resources\GuruResource;
use App\Http\Requests\StoreGuruRequest;
use App\Http\Requests\UpdateGuruRequest;
use App\Exports\GuruExport;
use App\Imports\GuruImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class GuruController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'import', 'bulkDelete']);
        $this->authorizeResource(GuruStaf::class, 'guru');
    }

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('q');
        $jabatan = $request->query('jabatan_fungsional');
        $status = $request->query('status_kepegawaian');
        $jurusan = $request->query('jurusan_id');
        $jk = $request->query('jenis_kelamin');
        $agama = $request->query('agama');
        $active = $request->has('is_active') ? $request->query('is_active') : 1;

        $data = GuruStaf::with(['jurusan', 'user'])
            ->when($search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('nip', 'like', "%{$search}%")
                      ->orWhere('nuptk', 'like', "%{$search}%");
                });
            })
            ->when($jabatan, fn($q) => $q->where('jabatan_fungsional', $jabatan))
            ->when($status, fn($q) => $q->where('status_kepegawaian', $status))
            ->when($jurusan, fn($q) => $q->where('jurusan_id', $jurusan))
            ->when($jk, fn($q) => $q->where('jenis_kelamin', $jk))
            ->when($agama, fn($q) => $q->where('agama', $agama))
            ->when($active !== null, fn($q) => $q->where('is_active', $active))
            ->latest()
            ->paginate($request->query('per_page', 12));

        return response()->json([
            'success' => true,
            'data'    => GuruResource::collection($data),
            'meta'    => [
                'current_page'  => $data->currentPage(),
                'last_page'     => $data->lastPage(),
                'per_page'      => $data->perPage(),
                'total'         => $data->total(),
                'from'          => $data->firstItem(),
                'to'            => $data->lastItem(),
                'next_page_url' => $data->nextPageUrl(),
                'prev_page_url' => $data->previousPageUrl(),
                'path'          => $data->path(),
                'links'         => $data->linkCollection()->toArray(),
            ],
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $this->authorize('export', GuruStaf::class);

        try {
            $filters = $request->only(['q', 'jabatan_fungsional', 'status_kepegawaian', 'jurusan_id', 'is_active', 'semester_id', 'jenis_kelamin', 'agama']);
            
            if (!isset($filters['is_active'])) {
                $filters['is_active'] = 1;
            }

            $nameParts = ['DATA_GURU_STAF'];

            if (!empty($filters['semester_id'])) {
                $semesterData = DB::table('semesters')
                    ->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                    ->where('semesters.id', $filters['semester_id'])
                    ->select('semesters.nama as nama_semester', 'tahun_ajaran.nama as nama_ta')
                    ->first();
                if ($semesterData) {
                    $taClean = str_replace(['/', ' '], '_', $semesterData->nama_ta);
                    $semClean = strtoupper(str_replace(' ', '_', $semesterData->nama_semester));
                    $nameParts[] = "{$taClean}_{$semClean}";
                }
            } else {
                $semesterAktif = DB::table('semesters')
                    ->join('tahun_ajaran', 'semesters.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                    ->where('semesters.is_active', 1)
                    ->select('semesters.nama as nama_semester', 'tahun_ajaran.nama as nama_ta')
                    ->first();
                
                if ($semesterAktif) {
                    $taClean = str_replace(['/', ' '], '_', $semesterAktif->nama_ta);
                    $semClean = strtoupper(str_replace(' ', '_', $semesterAktif->nama_semester));
                    $nameParts[] = "{$taClean}_{$semClean}";
                }
            }

            if (!empty($filters['q'])) {
                $nameParts[] = strtoupper(str_replace(' ', '_', $filters['q']));
            }

            if (!empty($filters['jabatan_fungsional'])) {
                $nameParts[] = strtoupper(str_replace(' ', '_', $filters['jabatan_fungsional']));
            }

            if (!empty($filters['jenis_kelamin'])) {
                $nameParts[] = $filters['jenis_kelamin'] == 'L' ? 'LAKI_LAKI' : 'PEREMPUAN';
            }

            if (!empty($filters['agama'])) {
                $nameParts[] = strtoupper($filters['agama']);
            }

            if (!empty($filters['jurusan_id'])) {
                $jurusan = Jurusan::find($filters['jurusan_id']);
                if ($jurusan) {
                    $cleanJurusan = strtoupper(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9 ]/', '', $jurusan->nama_jurusan)));
                    $nameParts[] = $cleanJurusan;
                }
            }

            if (isset($filters['is_active'])) {
                $nameParts[] = ($filters['is_active'] == 1 || $filters['is_active'] === '1') ? 'AKTIF' : 'NON_AKTIF';
            }
            
            $profil = ProfilSekolah::first();
            $kontak = DataKontak::first();

            $fileName = implode('_', $nameParts) . '.xlsx';
            
            if (ob_get_contents()) ob_end_clean();

            return Excel::download(new GuruExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Guru Error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Gagal mengekspor data guru.'], 500);
        }
    }

    public function importPreview(Request $request): JsonResponse
    {
        $this->authorize('import', GuruStaf::class);

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            $rows = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\WithHeadingRow {
                public function headingRow(): int { return 1; }
            }, $request->file('file'))[0] ?? [];

            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File kosong atau format tidak sesuai.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $activeJurusans = Jurusan::where('is_active', 1)
                ->get(['id', 'nama_jurusan'])
                ->mapWithKeys(function ($item) {
                    return [strtolower($item->nama_jurusan) => $item->id];
                })->toArray();

            $existingGuruData = GuruStaf::get(['id', 'nip', 'nuptk', 'email']);
            
            $existingByNip = [];
            $existingByNuptk = [];
            $existingByEmail = [];

            foreach ($existingGuruData as $guru) {
                if ($guru->nip) $existingByNip[$guru->nip] = $guru->id;
                if ($guru->nuptk) $existingByNuptk[$guru->nuptk] = $guru->id;
                if ($guru->email) $existingByEmail[strtolower($guru->email)] = $guru->id;
            }

            $previewData = [];
            $processedNips = [];
            $processedNuptks = [];
            $processedEmails = [];

            foreach ($rows as $row) {
                $nip = isset($row['nip']) ? trim($row['nip']) : null;
                $nuptk = isset($row['nuptk']) ? trim($row['nuptk']) : null;
                $nama = isset($row['nama']) ? trim($row['nama']) : null;
                $email = isset($row['email']) ? trim($row['email']) : null;
                $namaJurusan = isset($row['jurusan']) ? trim($row['jurusan']) : null;

                $status = 'CREATE';
                $existingId = null;
                $notes = [];
                $isValid = true;
                $emailConflict = false;
                $jurusanFound = false;

                if (empty($nama)) {
                    $isValid = false;
                    $notes[] = 'Nama kosong';
                }

                if ($nip) {
                    if (in_array($nip, $processedNips)) {
                        $notes[] = 'NIP duplikat di dalam file';
                        $isValid = false;
                    } else {
                        $processedNips[] = $nip;
                    }
                }

                if ($nuptk) {
                    if (in_array($nuptk, $processedNuptks)) {
                        $notes[] = 'NUPTK duplikat di dalam file';
                        $isValid = false;
                    } else {
                        $processedNuptks[] = $nuptk;
                    }
                }

                $lowerEmail = $email ? strtolower($email) : null;
                if ($lowerEmail) {
                    if (in_array($lowerEmail, $processedEmails)) {
                        $notes[] = 'Email duplikat di dalam file';
                        $isValid = false;
                    } else {
                        $processedEmails[] = $lowerEmail;
                    }
                }

                if ($nip && isset($existingByNip[$nip])) {
                    $existingId = $existingByNip[$nip];
                    $status = 'UPDATE';
                } elseif ($nuptk && isset($existingByNuptk[$nuptk])) {
                    $existingId = $existingByNuptk[$nuptk];
                    $status = 'UPDATE';
                }

                if ($lowerEmail && isset($existingByEmail[$lowerEmail])) {
                    $emailOwnerId = $existingByEmail[$lowerEmail];
                    if ($existingId === null || $emailOwnerId != $existingId) {
                        $emailConflict = true;
                        $notes[] = 'Email sudah digunakan data lain';
                        $isValid = false;
                    }
                }

                if ($namaJurusan) {
                    $keyJurusan = strtolower($namaJurusan);
                    if (isset($activeJurusans[$keyJurusan])) {
                        $jurusanFound = true;
                    } else {
                        $notes[] = "Jurusan '$namaJurusan' tidak aktif/ditemukan";
                        $isValid = false;
                    }
                } else {
                    $jurusanFound = true;
                }

                $row['is_duplicate'] = $existingId ? true : false;
                $row['email_conflict'] = $emailConflict;
                $row['jurusan_found'] = $jurusanFound;
                $row['is_valid'] = $isValid;
                $row['import_action'] = $status;
                $row['import_notes'] = implode(', ', $notes);

                $previewData[] = $row;
            }

            return response()->json([
                'success' => true,
                'data' => $previewData
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Preview Import Guru Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca file import: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('import', GuruStaf::class);

        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            $import = new GuruImport();
            Excel::import($import, $request->file('file'));
            $conflicts = $import->getMessages();

            return response()->json([
                'success' => true,
                'message' => count($conflicts) > 0 
                            ? 'Import selesai dengan beberapa catatan' 
                            : 'Data guru berhasil diimport secara massal.',
                'conflicts' => $conflicts
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Guru Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal import',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

      public function bulkDelete(Request $request): JsonResponse
{
    $this->authorize('deleteAny', GuruStaf::class);

    $request->validate([
        'ids' => 'required|array',
        'ids.*' => 'exists:guru_staf,id'
    ]);

    try {
        DB::transaction(function () use ($request) {
            $gurus = GuruStaf::with('user')->whereIn('id', $request->ids)->get();
            
            /** @var \App\Models\GuruStaf $guru */
            foreach ($gurus as $guru) {
                $hasRelation = DB::table('guru_mapel')->where('guru_staf_id', $guru->id)->exists() ||
                               DB::table('kelas_wali_kelas')->where('guru_staf_id', $guru->id)->exists() ||
                               DB::table('presensi_guru_mapel')->where('guru_staf_id', $guru->id)->exists() ||
                               DB::table('presensi')->where('guru_id', $guru->id)->exists() ||
                               DB::table('poin_siswa')->where('guru_staf_id', $guru->id)->exists();

                if ($hasRelation) {
                    $guru->update(['is_active' => 0]);
                    if ($guru->user) {
                        $guru->user->update(['is_active' => 0]);
                    }
                } else {
                    $fotoPath = $guru->foto;
                    $userId = $guru->user_id;

                    if ($userId) {
                        DB::table('user_roles')->where('user_id', $userId)->delete();
                        User::where('id', $userId)->delete();
                    }

                    if ($fotoPath) {
                        $filePath = public_path('uploads/guru/') . str_replace('uploads/guru/', '', $fotoPath);
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }

                    $guru->delete();
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Data guru terpilih berhasil diproses (dihapus atau dinonaktifkan).',
        ], Response::HTTP_OK);

    } catch (Throwable $e) {
        Log::error('Bulk Delete Guru Error', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Gagal memproses data secara massal.',
            'errors' => $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GuruStaf::class);

        $search = $request->query('q');

        $gurus = GuruStaf::query()
            ->where('is_active', 1)
            ->when($search, function ($query, $search) {
                $query->where('nama', 'like', "%{$search}%")
                      ->orWhere('nip', 'like', "%{$search}%");
            })
            ->select('id', 'nama', 'nip')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $gurus,
        ], Response::HTTP_OK);
    }

    public function show(?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
                'errors'  => ['id' => ['Guru dengan ID tersebut tidak ada']],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data'    => new GuruResource($guru->load(['jurusan', 'user'])),
        ], Response::HTTP_OK);
    }

    public function store(StoreGuruRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $targetPath = public_path('uploads/guru');

        if (!empty($validated['jurusan_id'])) {
            $jurusanAktif = Jurusan::where('id', $validated['jurusan_id'])->where('is_active', 1)->exists();
            if (!$jurusanAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membuat data guru.',
                    'errors'  => ['jurusan_id' => ['Jurusan yang dipilih tidak aktif atau tidak ditemukan.']],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            $guru = DB::transaction(function () use ($request, $validated, $targetPath) {
                $lastUser = User::where('id', 'like', 'U%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->lockForUpdate()
                    ->first();
                
                $lastId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
                $newUserId = 'U' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);

                $usernameBase = !empty($validated['nip']) ? trim($validated['nip']) : strtolower(str_replace(' ', '', $validated['nama']));
                $finalUsername = $usernameBase;
                $count = 1;
                while (User::where('username', $finalUsername)->exists()) {
                    $finalUsername = $usernameBase . $count;
                    $count++;
                }

                User::create([
                    'id'           => $newUserId,
                    'username'     => $finalUsername, 
                    'password'     => Hash::make($finalUsername),
                    'current_role' => 'Guru',
                    'is_active'    => 1,
                ]);

                DB::table('user_roles')->insert([
                    'user_id'    => $newUserId,
                    'role_id'    => 'R002', 
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($request->hasFile('foto')) {
                    $file = $request->file('foto');
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $file->move($targetPath, $fileName);
                    $validated['foto'] = $fileName;
                }

                $validated['user_id'] = $newUserId;
                $validated['is_active'] = 1;

                return GuruStaf::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil ditambahkan.',
                'data'    => new GuruResource($guru->load(['jurusan', 'user'])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create guru', ['error' => $e->getMessage()]);

            if (!empty($validated['foto'])) {
                $filePath = $targetPath . '/' . $validated['foto'];
                if (file_exists($filePath)) unlink($filePath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat data guru.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateGuruRequest $request, ?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $targetPath = public_path('uploads/guru');
        $oldFoto = $guru->foto;

        if (!empty($validated['jurusan_id'])) {
            $jurusanAktif = Jurusan::where('id', $validated['jurusan_id'])->where('is_active', 1)->exists();
            if (!$jurusanAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memperbarui data guru.',
                    'errors'  => ['jurusan_id' => ['Jurusan yang dipilih tidak aktif atau tidak ditemukan.']],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->move($targetPath, $fileName);
            $validated['foto'] = $fileName;
        }

        if ($request->filled('foto') && $request->foto === 'null') {
            if ($oldFoto) {
                $oldPath = $targetPath . '/' . str_replace('uploads/guru/', '', $oldFoto);
                if (file_exists($oldPath)) unlink($oldPath);
            }
            $validated['foto'] = null;
        }

        try {
            DB::transaction(function () use ($guru, $validated) {
                $isReactivating = isset($validated['is_active']) && $guru->is_active == 0 && $validated['is_active'] == 1;

                $guru->update($validated);

                if ($guru->user) {
                    $userData = [];
                    
                    if (isset($validated['is_active'])) {
                        $userData['is_active'] = $validated['is_active'];
                        
                        if ($isReactivating) {
                            $userData['password'] = Hash::make($guru->user->username);
                        }
                    }

                    if (!empty($validated['nip'])) {
                        $newNip = trim($validated['nip']);
                        $userData['username'] = $newNip;
                        $userData['password'] = Hash::make($newNip);
                    }

                    if (!empty($userData)) {
                        $guru->user->update($userData);
                    }
                }
            });

            if (!empty($validated['foto']) && $oldFoto && $oldFoto !== $validated['foto']) {
                $oldPath = $targetPath . '/' . str_replace('uploads/guru/', '', $oldFoto);
                if (file_exists($oldPath)) unlink($oldPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data guru berhasil diperbarui.',
                'data'    => new GuruResource($guru->refresh()->load(['jurusan', 'user'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update guru', ['error' => $e->getMessage()]);

            if (!empty($validated['foto']) && $validated['foto'] !== $oldFoto) {
                $tempPath = $targetPath . '/' . $validated['foto'];
                if (file_exists($tempPath)) unlink($tempPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data guru.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(?GuruStaf $guru): JsonResponse
    {
        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak ditemukan',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            DB::transaction(function () use ($guru) {
                $hasRelation = DB::table('guru_mapel')->where('guru_staf_id', $guru->id)->exists() ||
                               DB::table('kelas_wali_kelas')->where('guru_staf_id', $guru->id)->exists() ||
                               DB::table('presensi_guru_mapel')->where('guru_staf_id', $guru->id)->exists() ||
                               DB::table('presensi')->where('guru_id', $guru->id)->exists() ||
                               DB::table('poin_siswa')->where('guru_staf_id', $guru->id)->exists();

                if ($hasRelation) {
                    $guru->update(['is_active' => 0]);
                    if ($guru->user) {
                        $guru->user->update(['is_active' => 0]);
                    }
                } else {
                    $fotoPath = $guru->foto;
                    $userId = $guru->user_id;

                    if ($userId) {
                        DB::table('user_roles')->where('user_id', $userId)->delete();
                        User::where('id', $userId)->delete();
                    }

                    $guru->delete();

                    if ($fotoPath) {
                        $filePath = public_path('uploads/guru/') . str_replace('uploads/guru/', '', $fotoPath);
                        if (file_exists($filePath)) unlink($filePath);
                    }
                }
            });

            return response()->json([
                'success'      => true,
                'message'      => 'Data guru berhasil diproses (dihapus atau dinonaktifkan)',
                'notification' => 'Berhasil diproses',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete guru', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses data guru',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}