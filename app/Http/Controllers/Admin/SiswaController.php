<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, User, SiswaKelas, ProfilSekolah, DataKontak};
use App\Http\Requests\{StoreSiswaRequest, UpdateSiswaRequest};
use App\Http\Resources\SiswaResource;
use App\Exports\SiswaExport;
use App\Imports\SiswaImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Hash, File};
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
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'import', 'bulkDestroy']);
        $this->authorizeResource(Siswa::class, 'siswa');
    }

    private function applyFilters(Request $request, $query)
    {
        if ($request->filled('jurusan_id')) {
            $query->whereHas('riwayatKelas.kelas', fn($q) => $q->where('jurusan_id', $request->jurusan_id));
        }

        if ($request->filled('tingkatan_id')) {
            $query->whereHas('riwayatKelas.kelas', fn($q) => $q->where('tingkatan_id', $request->tingkatan_id));
        }

        if ($request->filled('kelas_id')) {
            $query->whereHas('riwayatKelas', fn($q) => $q->where('kelas_id', $request->kelas_id));
        }

        if ($request->filled('jenis_kelamin')) {
            $query->where('jenis_kelamin', $request->jenis_kelamin);
        }

        if ($request->filled('agama')) {
            $query->where('agama', $request->agama);
        }

        if ($request->filled('tahun_angkatan')) {
            $query->where('tahun_angkatan', $request->tahun_angkatan);
        }

        if ($request->filled('semester_id')) {
            $query->whereHas('riwayatKelas', function($q) use ($request) {
                $q->where('semester_id', $request->semester_id);
            });
        } else {
            $query->whereHas('riwayatKelas', function($q) {
                $q->whereHas('semester', fn($sem) => $sem->where('is_active', 1));
            });
        }

        if ($request->has('is_active') && $request->is_active !== null && $request->is_active !== '') {
            $query->where('siswa.is_active', $request->is_active);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhereHas('riwayatKelas.kelas', function ($qK) use ($search) {
                      $qK->where('nama_kelas', 'like', "%{$search}%");
                  });
            });
        }

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Siswa::with(['user', 'riwayatKelas.kelas.jurusan', 'orangtua']);
        $query = $this->applyFilters($request, $query);

        $perPage = $request->query('per_page', $request->filled('search') ? 10 : 20);
        
        $items = $query->orderByRaw('LOWER(nama_lengkap) ASC')->paginate($perPage);
        $paginationData = $items->toArray();

        $semesterAktif = DB::table('semesters')->where('is_active', 1)->first();

        return response()->json([
            'success' => true,
            'data'    => SiswaResource::collection($items),
            'meta'    => [
                'current_page'  => $paginationData['current_page'],
                'last_page'     => $paginationData['last_page'],
                'per_page'      => $paginationData['per_page'],
                'total'         => $paginationData['total'],
                'from'          => $paginationData['from'],
                'to'            => $paginationData['to'],
                'path'          => $paginationData['path'],
                'next_page_url' => $paginationData['next_page_url'],
                'prev_page_url' => $paginationData['prev_page_url'],
                'links'         => array_map(function ($link) {
                    return [
                        'url'    => $link['url'],
                        'label'  => $link['label'],
                        'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
                        'active' => $link['active'],
                    ];
                }, $paginationData['links']),
                'semester_aktif' => $semesterAktif ? $semesterAktif->nama : null
            ],
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Siswa::class);

        $query = Siswa::query()->with(['riwayatKelas.kelas.jurusan', 'orangtua']);
        $query = $this->applyFilters($request, $query);
        $query->orderByRaw('LOWER(nama_lengkap) ASC');

        $filters = $request->all();

        if ($request->filled('jurusan_id')) {
            $filters['nama_jurusan'] = DB::table('jurusan')->where('id', $request->jurusan_id)->value('nama_jurusan');
        } else {
            $filters['nama_jurusan'] = 'SEMUA';
        }

        if ($request->filled('tingkatan_id')) {
            $tingkatData = DB::table('tingkatan')->where('id', $request->tingkatan_id)->first();
            $filters['nama_tingkatan'] = $tingkatData ? $tingkatData->nama_tingkatan : 'SEMUA';
        } else {
            $filters['nama_tingkatan'] = 'SEMUA';
        }

        if ($request->filled('kelas_id')) {
            $kelasDataObj = Kelas::find($request->kelas_id);
            $filters['nama_kelas'] = $kelasDataObj ? $kelasDataObj->nama_kelas : 'SEMUA KELAS';
        } else {
            $filters['nama_kelas'] = 'SEMUA KELAS';
            $kelasDataObj = null;
        }

        if ($request->filled('semester_id')) {
            $semesterFocus = DB::table('semesters')->where('id', $request->semester_id)->first();
        } else {
            $semesterFocus = DB::table('semesters')->where('is_active', 1)->first();
        }

        $tahunAjaranAktif = DB::table('tahun_ajaran')->where('is_active', 1)->first();
        $labelTahun = $tahunAjaranAktif ? strtoupper(str_replace(['/', '-'], '_', $tahunAjaranAktif->nama)) : 'TAHUN_TIDAK_DIKETAHUI';

        $labelPeriode = 'PERIODE_TIDAK_DIKETAHUI';
        if ($semesterFocus) {
            $labelPeriode = strtoupper(Str::slug($semesterFocus->nama, '_'));
        }

        $filename = 'DATA_SISWA';

        if ($request->filled('tingkatan_id')) {
            $tingkatLabel = DB::table('tingkatan')->where('id', $request->tingkatan_id)->value('nama_tingkatan');
            if ($tingkatLabel) {
                $filename .= '_TINGKAT_' . strtoupper($tingkatLabel);
            }
        }

        if ($kelasDataObj) {
            $filename .= '_' . strtoupper(Str::slug($kelasDataObj->nama_kelas, '_'));
        }

        if ($request->filled('tahun_angkatan')) {
            $filename .= '_ANGKATAN_' . $request->tahun_angkatan;
        }

        if ($request->filled('agama')) {
            $filename .= '_' . strtoupper($request->agama);
        }

        if ($request->filled('jenis_kelamin')) {
            $filename .= '_' . strtoupper($request->jenis_kelamin);
        }

        $filename .= '_' . $labelTahun . '_' . $labelPeriode;
        
        if ($request->has('is_active') && $request->is_active !== null && $request->is_active !== '') {
            $filename .= $request->is_active ? '_AKTIF' : '_TIDAK_AKTIF';
        }

        $filename .= '.xlsx';

        $profil = ProfilSekolah::first() ?? new ProfilSekolah();
        $contak = DataKontak::first() ?? new DataKontak();

        if (ob_get_contents()) ob_end_clean();

        return Excel::download(
            new SiswaExport($query, $profil, $contak, $kelasDataObj, $filters), 
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

 public function importPreview(Request $request): JsonResponse
{
    $this->authorize('create', Siswa::class);
    $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

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

        $semesterAktif = DB::table('semesters')->where('is_active', 1)->first();
        
        $existingStudents = Siswa::select('nis', 'nik', 'nisn', 'is_active')->get();
        $existingNis = $existingStudents->pluck('is_active', 'nis')->toArray();
        $existingNik = $existingStudents->whereNotNull('nik')->pluck('is_active', 'nik')->toArray();
        $existingNisn = $existingStudents->whereNotNull('nisn')->pluck('is_active', 'nisn')->toArray();

        $activeClasses = Kelas::where('is_active', 1)
            ->get(['id', 'nama_kelas'])
            ->flatMap(function ($item) {
                return [
                    strtolower($item->nama_kelas) => true,
                    (string)$item->id => true
                ];
            })->toArray();

        $previewData = [];
        $processedNis = [];
        $processedNik = [];
        $processedNisn = [];

        if (!$semesterAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada semester aktif. Silakan aktifkan semester terlebih dahulu.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($rows as $row) {
            $nis = isset($row['nis']) ? trim((string)$row['nis']) : null;
            $nik = isset($row['nik']) ? trim((string)$row['nik']) : null;
            $nisn = isset($row['nisn']) ? trim((string)$row['nisn']) : null;
            $nama = isset($row['nama_lengkap']) ? trim((string)$row['nama_lengkap']) : null;
            $kelasInput = isset($row['kelas']) ? trim((string)$row['kelas']) : null;

            $status = 'New';
            $notes = [];
            $isValid = true;
            
            $isNisError = false;
            $isNikError = false;
            $isNisnError = false;
            $isKelasError = false;

            if (!$nis || !$nama) {
                $isValid = false;
                $notes[] = 'NIS atau Nama kosong';
                $isNisError = !$nis;
            }

            if ($nis) {
                if (in_array($nis, $processedNis)) {
                    $notes[] = 'NIS duplikat di file';
                    $isValid = false;
                    $isNisError = true;
                } else {
                    $processedNis[] = $nis;
                }

                if (isset($existingNis[$nis])) {
                    $status = $existingNis[$nis] ? 'Update' : 'Reactivate';
                }
            }

            if ($nik) {
                if (in_array($nik, $processedNik)) {
                    $notes[] = 'NIK duplikat di file';
                    $isValid = false;
                    $isNikError = true;
                } else {
                    $processedNik[] = $nik;
                }

                if (isset($existingNik[$nik]) && $status === 'New') {
                    $notes[] = 'NIK sudah ada di database';
                    $isValid = false;
                    $isNikError = true;
                }
            }

            if ($nisn) {
                if (in_array($nisn, $processedNisn)) {
                    $notes[] = 'NISN duplikat di file';
                    $isValid = false;
                    $isNisnError = true;
                } else {
                    $processedNisn[] = $nisn;
                }

                if (isset($existingNisn[$nisn]) && $status === 'New') {
                    $notes[] = 'NISN sudah ada di database';
                    $isValid = false;
                    $isNisnError = true;
                }
            }

            if ($kelasInput) {
                if (!isset($activeClasses[strtolower($kelasInput)]) && !isset($activeClasses[$kelasInput])) {
                    $notes[] = "Kelas '$kelasInput' tidak aktif/ditemukan";
                    $isValid = false;
                    $isKelasError = true;
                }
            } else {
                $notes[] = 'Kelas kosong';
                $isValid = false;
                $isKelasError = true;
            }

            $row['import_status'] = $status;
            $row['import_notes'] = implode(', ', $notes);
            $row['is_valid'] = $isValid;
            $row['is_nis_error'] = $isNisError;
            $row['is_nik_error'] = $isNikError;
            $row['is_nisn_error'] = $isNisnError;
            $row['is_kelas_error'] = $isKelasError;
            
            $previewData[] = $row;
        }

        return response()->json([
            'success' => true,
            'data' => $previewData
        ], Response::HTTP_OK);

    } catch (Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal membaca preview: ' . $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    public function bulkDelete(Request $request): JsonResponse
    {
        $this->authorize('bulkDestroy', Siswa::class);
        $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:siswa,id']);

        try {
            $results = DB::transaction(function() use ($request) {
                $ids = $request->ids;
                $students = Siswa::with(['orangtua', 'presensiDetail', 'presensiSiswaDetail'])->whereIn('id', $ids)->get();
                $countDeleted = 0;
                $countDeactivated = 0;

                foreach ($students as $siswa) {
                    $hasPresensi = $siswa->presensiDetail()->exists() || $siswa->presensiSiswaDetail()->exists();
                    $oldFoto = $siswa->foto;
                    $userId = $siswa->user_id;

                    if ($hasPresensi) {
                        SiswaKelas::where('siswa_id', $siswa->id)->update(['is_active' => 0]);
                        foreach ($siswa->orangtua as $ot) {
                            DB::table('orangtua')->where('id', $ot->id)->update(['is_active' => 0]);
                            if ($ot->user_id) User::where('id', $ot->user_id)->update(['is_active' => 0]);
                        }
                        $siswa->update(['is_active' => 0]);
                        if ($userId) User::where('id', $userId)->update(['is_active' => 0]);
                        $countDeactivated++;
                    } else {
                        SiswaKelas::where('siswa_id', $siswa->id)->delete();
                        $siswa->orangtua()->detach();
                        if ($userId) {
                            User::where('id', $userId)->delete();
                        }
                        $siswa->delete();
                        
                        if ($oldFoto) {
                            $cleanName = str_replace(['uploads/siswa/', 'siswa/', 'foto/'], '', $oldFoto);
                            $filePath = public_path('uploads/siswa/') . $cleanName;
                            if (File::exists($filePath)) File::delete($filePath);
                        }
                        $countDeleted++;
                    }
                }
                return ['deleted' => $countDeleted, 'deactivated' => $countDeactivated];
            });

            return response()->json([
                'success' => true,
                'message' => "Berhasil memproses data: {$results['deleted']} dihapus permanen, {$results['deactivated']} dinonaktifkan"
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error("Bulk Delete Siswa Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses data massal'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Siswa $siswa): JsonResponse
    {
        $siswa->load(['user', 'riwayatKelas.kelas.jurusan', 'orangtua']);
        return response()->json([
            'success' => true,
            'data'    => new SiswaResource($siswa)
        ], Response::HTTP_OK);
    }

    public function store(StoreSiswaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $semesterAktif = DB::table('semesters')->where('is_active', 1)->first();
        $targetPath = public_path('uploads/siswa');

        if (!$semesterAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada semester aktif yang ditemukan.'
            ], 422);
        }

        $data = Arr::only($validated, (new Siswa())->getFillable());

        try {
            $siswa = DB::transaction(function() use ($data, $validated, $semesterAktif, $request, $targetPath) {
                if (User::where('username', $data['nis'])->exists()) {
                    throw ValidationException::withMessages([
                        'nis' => ["NIS {$data['nis']} sudah terdaftar sebagai pengguna lain."]
                    ]);
                }

                if ($request->hasFile('foto')) {
                    if (!File::exists($targetPath)) File::makeDirectory($targetPath, 0755, true);
                    
                    $file = $request->file('foto');
                    $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->move($targetPath, $fileName);
                    $data['foto'] = 'siswa/' . $fileName;
                }

                $lastUser = User::where('id', 'like', 'U%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->lockForUpdate()
                    ->first();
                $lastUserId = $lastUser ? (int) substr($lastUser->id, 1) : 0;
                $newUserId = 'U' . str_pad($lastUserId + 1, 3, '0', STR_PAD_LEFT);

                User::create([
                    'id'           => $newUserId,
                    'username'     => $data['nis'],
                    'password'     => Hash::make($data['nis']),
                    'current_role' => 'Siswa',
                    'is_active'    => 1,
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

                $siswaCreated = Siswa::create($data);

                SiswaKelas::create([
                    'siswa_id'    => $newSiswaId,
                    'kelas_id'    => $validated['kelas_id'],
                    'semester_id' => $semesterAktif->id,
                    'is_active'   => 1
                ]);

                return $siswaCreated;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil ditambahkan',
                'data'    => new SiswaResource($siswa->load(['user', 'riwayatKelas.kelas.jurusan', 'orangtua']))
            ], Response::HTTP_CREATED);

        } catch (ValidationException $e) {
            if (isset($data['foto'])) {
                $tempFile = str_replace('siswa/', '', $data['foto']);
                if (File::exists($targetPath . '/' . $tempFile)) File::delete($targetPath . '/' . $tempFile);
            }
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            if (isset($data['foto'])) {
                $tempFile = str_replace('siswa/', '', $data['foto']);
                if (File::exists($targetPath . '/' . $tempFile)) File::delete($targetPath . '/' . $tempFile);
            }
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
        $semesterAktif = DB::table('semesters')->where('is_active', 1)->first();
        $targetPath = public_path('uploads/siswa');

        $data = Arr::only($validated, (new Siswa())->getFillable());
        $oldFoto = $siswa->foto;

        try {
            DB::transaction(function() use ($siswa, $data, $validated, $semesterAktif, $request, $targetPath, $oldFoto) {
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

                if ($request->hasFile('foto')) {
                    if (!File::exists($targetPath)) File::makeDirectory($targetPath, 0755, true);

                    $file = $request->file('foto');
                    $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->move($targetPath, $fileName);
                    $data['foto'] = 'siswa/' . $fileName;
                    
                    if ($oldFoto) {
                        $cleanOldName = str_replace(['uploads/siswa/', 'siswa/', 'foto/'], '', $oldFoto);
                        if (File::exists($targetPath . '/' . $cleanOldName)) File::delete($targetPath . '/' . $cleanOldName);
                    }
                }

                $oldIsActive = $siswa->is_active;
                $isReactivating = isset($data['is_active']) && $oldIsActive == 0 && $data['is_active'] == 1;

                $siswa->update($data);

                if (isset($data['is_active'])) {
                    $status = $data['is_active'];

                    if ($status == 0) {
                        SiswaKelas::where('siswa_id', $siswa->id)
                            ->where('is_active', 1)
                            ->update(['is_active' => 0]);
                    } elseif ($isReactivating) {
                        $lastHistory = SiswaKelas::where('siswa_id', $siswa->id)
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if ($lastHistory && $semesterAktif && $lastHistory->semester_id == $semesterAktif->id) {
                            DB::table('siswa_kelas')->where('id', $lastHistory->id)->update(['is_active' => 1]);
                        } elseif ($semesterAktif && isset($validated['kelas_id'])) {
                            SiswaKelas::create([
                                'siswa_id'    => $siswa->id,
                                'kelas_id'    => $validated['kelas_id'],
                                'semester_id' => $semesterAktif->id,
                                'is_active'   => 1
                            ]);
                        }
                    }

                    $siswa->load(['orangtua']);
                    foreach ($siswa->orangtua as $ot) {
                        DB::table('orangtua')->where('id', $ot->id)->update(['is_active' => $status]);
                        if ($ot->user_id) {
                            $otUser = User::find($ot->user_id);
                            if ($otUser) {
                                $otUser->is_active = $status;
                                if ($isReactivating) {
                                    $otUser->password = Hash::make($ot->telepon);
                                }
                                $otUser->save();
                            }
                        }
                    }
                }

                if (isset($validated['kelas_id']) && $semesterAktif && $siswa->is_active == 1) {
                    $currentKelas = SiswaKelas::where('siswa_id', $siswa->id)
                        ->where('is_active', 1)
                        ->first();

                    if (!$currentKelas || $currentKelas->kelas_id != $validated['kelas_id']) {
                        SiswaKelas::where('siswa_id', $siswa->id)
                            ->where('is_active', 1)
                            ->update(['is_active' => 0]);

                        SiswaKelas::updateOrCreate(
                            ['siswa_id' => $siswa->id, 'kelas_id' => $validated['kelas_id'], 'semester_id' => $semesterAktif->id],
                            ['is_active' => 1]
                        );
                    }
                }

                if ($siswa->user_id) {
                    $user = User::find($siswa->user_id);
                    if ($user) {
                        if (isset($data['nis'])) {
                            $user->username = $data['nis'];
                            $user->password = Hash::make($data['nis']);
                        }
                        if (isset($data['is_active'])) {
                            $user->is_active = $data['is_active'];
                            if ($isReactivating && !isset($data['nis'])) {
                                $user->password = Hash::make($siswa->nis);
                            }
                        }
                        $user->save();
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diperbarui',
                'data'    => new SiswaResource($siswa->fresh(['user', 'riwayatKelas.kelas.jurusan', 'orangtua']))
            ], Response::HTTP_OK);

        } catch (ValidationException $e) {
            if ($request->hasFile('foto') && isset($data['foto'])) {
                $tempFile = str_replace('siswa/', '', $data['foto']);
                if (File::exists($targetPath . '/' . $tempFile)) File::delete($targetPath . '/' . $tempFile);
            }
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            if ($request->hasFile('foto') && isset($data['foto'])) {
                $tempFile = str_replace('siswa/', '', $data['foto']);
                if (File::exists($targetPath . '/' . $tempFile)) File::delete($targetPath . '/' . $tempFile);
            }
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
            $hasPresensi = $siswa->presensiDetail()->exists() || $siswa->presensiSiswaDetail()->exists();
            $oldFoto = $siswa->foto;
            $userId = $siswa->user_id;

            DB::transaction(function() use ($siswa, $userId, $hasPresensi, $oldFoto) {
                if ($hasPresensi) {
                    SiswaKelas::where('siswa_id', $siswa->id)->update(['is_active' => 0]);
                    $siswa->load(['orangtua']);
                    foreach ($siswa->orangtua as $ot) {
                        DB::table('orangtua')->where('id', $ot->id)->update(['is_active' => 0]);
                        if ($ot->user_id) User::where('id', $ot->user_id)->update(['is_active' => 0]);
                    }
                    $siswa->update(['is_active' => 0]);
                    if ($userId) User::where('id', $userId)->update(['is_active' => 0]);
                } else {
                    SiswaKelas::where('siswa_id', $siswa->id)->delete();
                    $siswa->orangtua()->detach();
                    if ($userId) User::where('id', $userId)->delete();
                    $siswa->delete();
                    
                    if ($oldFoto) {
                        $cleanOldName = str_replace(['uploads/siswa/', 'siswa/', 'foto/'], '', $oldFoto);
                        $filePath = public_path('uploads/siswa/') . $cleanOldName;
                        if (File::exists($filePath)) File::delete($filePath);
                    }
                }
            });

            $msg = $hasPresensi ? 'Data siswa dinonaktifkan karena memiliki riwayat presensi' : 'Data siswa berhasil dihapus permanen';

            return response()->json([
                'success' => true, 
                'message' => $msg
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