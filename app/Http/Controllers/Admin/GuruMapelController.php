<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuruMapel;
use App\Models\JamSekolah;
use App\Models\TahunAjaran;
use App\Models\GuruStaf;
use App\Models\MataPelajaran;
use App\Models\Kelas;
use App\Models\ProfilSekolah;
use App\Models\DataKontak;
use App\Http\Resources\GuruMapelResource;
use App\Http\Requests\StoreGuruMapelRequest;
use App\Http\Requests\UpdateGuruMapelRequest;
use App\Exports\GuruMapelExport;
use App\Imports\GuruMapelImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class GuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy', 'import']);
        $this->authorizeResource(GuruMapel::class, 'guru_mapel');
    }

    private function applyFilters(Request $request)
    {
        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran', 'jamMulai', 'jamSelesai']);

        if (!$request->has('show_all')) {
            $query->whereHas('mapel', function ($q) {
                $q->where('is_active', 1);
            });
        }

        $query->when($request->tipe_mapel, function ($q, $tipe) {
            return $q->whereHas('mapel', fn($m) => $m->where('tipe_mapel', $tipe));
        });

        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $tahunAjaranId = $request->get('tahun_ajaran_id', optional($tahunAktif)->id);

        if ($request->filled('q')) {
            $search = $request->get('q');
            $query->where(function ($q) use ($search) {
                $q->whereHas('guru', fn($g) => $g->where('nama', 'LIKE', "%{$search}%")->orWhere('nip', 'LIKE', "%{$search}%"))
                  ->orWhereHas('mapel', fn($m) => $m->where('nama_mapel', 'LIKE', "%{$search}%"))
                  ->orWhereHas('kelas', fn($k) => $k->where('nama_kelas', 'LIKE', "%{$search}%"));
            });
        }

        $query->when($tahunAjaranId, fn($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
              ->when($request->guru_staf_id, fn($q, $id) => $q->where('guru_staf_id', $id))
              ->when($request->mata_pelajaran_id, fn($q, $id) => $q->where('mata_pelajaran_id', $id))
              ->when($request->kelas_id, fn($q, $id) => $q->where('kelas_id', $id))
              ->when($request->hari, fn($q, $hari) => $q->where('hari', $hari));

        return $query->orderBy(DB::raw("FIELD(hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu')"))
                     ->orderBy('jam_mulai_id');
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->applyFilters($request);
        $perPage = $request->get('per_page', 10);
        $assignments = $query->paginate($perPage);
        $paginationData = $assignments->toArray();

        return response()->json([
            'success' => true,
            'data'    => GuruMapelResource::collection($assignments),
            'meta'    => [
                'current_page' => $assignments->currentPage(),
                'last_page'    => $assignments->lastPage(),
                'per_page'     => $assignments->perPage(),
                'total'        => $assignments->total(),
                'from'         => $assignments->firstItem(),
                'to'           => $assignments->lastItem(),
                'next_page_url'=> $assignments->nextPageUrl(),
                'prev_page_url'=> $assignments->previousPageUrl(),
                'path'         => $paginationData['path'],
                'links'        => $paginationData['links'],
            ],
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', GuruMapel::class);

        try {
            $query = $this->applyFilters($request);
            
            $kelasId = $request->get('kelas_id') ?? $request->get('klas_id');
            $guruId = $request->get('guru_staf_id') ?? $request->get('guru_id');

            $filters = [
                'q'            => $request->get('q'),
                'hari'         => $request->get('hari'),
                'tahun_ajaran' => 'Semua',
                'semester'     => 'Semua',
                'guru'         => 'Semua Guru',
                'mapel'        => 'Semua Mapel',
                'kelas'        => 'Semua Kelas',
                'tipe_mapel'   => $request->get('tipe_mapel', 'Semua Tipe'),
                'status_mapel' => $request->has('show_all') ? 'Semua (Aktif & Non-Aktif)' : 'Hanya Mapel Aktif'
            ];

            $nameParts = ['JADWAL_GURU_MAPEL'];

            if ($request->filled('tahun_ajaran_id')) {
                $tahun = TahunAjaran::find($request->tahun_ajaran_id);
            } else {
                $tahun = TahunAjaran::where('is_active', 1)->first();
            }

            if ($tahun) {
                $filters['tahun_ajaran'] = $tahun->nama;
                $filters['semester'] = strtoupper($tahun->semester);
                $taClean = str_replace(['/', ' '], '_', $tahun->nama);
                $semester = strtoupper($tahun->semester);
                $nameParts[] = "{$taClean}_{$semester}";
            }

            if ($request->filled('tipe_mapel')) {
                $nameParts[] = strtoupper(str_replace(' ', '_', $request->tipe_mapel));
            }

            if ($guruId) {
                $guru = GuruStaf::find($guruId);
                if ($guru) {
                    $filters['guru'] = $guru->nama;
                    $nameParts[] = strtoupper(str_replace(' ', '_', $guru->nama));
                }
            }

            if ($request->filled('mata_pelajaran_id')) {
                $mapel = MataPelajaran::find($request->mata_pelajaran_id);
                if ($mapel) {
                    $filters['mapel'] = $mapel->nama_mapel;
                    $nameParts[] = strtoupper(str_replace(' ', '_', $mapel->nama_mapel));
                }
            }

            if ($kelasId) {
                $kelas = Kelas::find($kelasId);
                if ($kelas) {
                    $filters['kelas'] = $kelas->nama_kelas;
                    $nameParts[] = strtoupper(str_replace(' ', '_', $kelas->nama_kelas));
                }
            }

            if ($request->filled('hari')) {
                $nameParts[] = strtoupper($request->hari);
            }

            $nameParts[] = 'AKTIF';

            $fileName = implode('_', $nameParts) . '.xlsx';

            $profil = ProfilSekolah::first();
            $contak = DataKontak::first();

            if (ob_get_contents()) ob_end_clean();
            
            return Excel::download(new GuruMapelExport($query, $profil, $contak, $filters), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Gagal mengekspor data penugasan.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', GuruMapel::class);

        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            $import = new GuruMapelImport();
            Excel::import($import, $request->file('file'));
            $conflicts = $import->getMessages();

            return response()->json([
                'success' => true,
                'message' => count($conflicts) > 0 
                            ? 'Import selesai with several notes' 
                            : 'Data penugasan guru berhasil diimport.',
                'conflicts' => $conflicts
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal import data',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(GuruMapel $guruMapel): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new GuruMapelResource($guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran', 'jamMulai', 'jamSelesai']))
        ], Response::HTTP_OK);
    }

    public function getJamByHari(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GuruMapel::class);

        $hari = $request->query('hari');
        $jam = JamSekolah::where('hari', $hari)->orderBy('waktu_mulai')->get();

        return response()->json(['success' => true, 'data' => $jam], Response::HTTP_OK);
    }

    public function store(StoreGuruMapelRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $mapel = MataPelajaran::find($validated['mata_pelajaran_id']);
        if (!$mapel || $mapel->is_active == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Mata pelajaran yang dipilih sudah tidak aktif.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $validated['tahun_ajaran_id'] = $validated['tahun_ajaran_id'] ?? optional($tahunAktif)->id;

        $jamMulai = JamSekolah::find($validated['jam_mulai_id']);
        $jamSelesai = JamSekolah::find($validated['jam_selesai_id']);

        if (!$jamMulai || !$jamSelesai) {
            return response()->json(['success' => false, 'message' => 'Data jam tidak valid.'], 422);
        }

        if ($jamMulai->hari !== $validated['hari'] || $jamSelesai->hari !== $validated['hari']) {
            return response()->json([
                'success' => false, 
                'message' => "Gagal: Jam yang dipilih tidak tersedia pada hari {$validated['hari']}."
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($jamSelesai->waktu_selesai <= $jamMulai->waktu_mulai) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Jam selesai harus lebih besar dari jam mulai.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $bentrok = GuruMapel::where('hari', $validated['hari'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->where(function ($q) use ($jamMulai, $jamSelesai) {
                $q->whereHas('jamMulai', function ($query) use ($jamSelesai) {
                    $query->where('waktu_mulai', '<', $jamSelesai->waktu_selesai);
                })->whereHas('jamSelesai', function ($query) use ($jamMulai) {
                    $query->where('waktu_selesai', '>', $jamMulai->waktu_mulai);
                });
            })
            ->where(function ($q) use ($validated) {
                $q->where('guru_staf_id', $validated['guru_staf_id'])
                  ->orWhere('kelas_id', $validated['kelas_id']);
            })
            ->first();

        if ($bentrok) {
            $type = $bentrok->guru_staf_id == $validated['guru_staf_id'] ? "Guru" : "Kelas";
            return response()->json([
                'success' => false, 
                'message' => "Jadwal Bentrok: {$type} sudah memiliki jadwal lain di jam tersebut."
            ], Response::HTTP_CONFLICT);
        }

        $exists = GuruMapel::where('guru_staf_id', $validated['guru_staf_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->where('kelas_id', $validated['kelas_id'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Guru sudah terdaftar pada mata pelajaran dan kelas ini.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $assignment = DB::transaction(fn() => GuruMapel::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru berhasil ditambahkan.',
                'data'    => new GuruMapelResource($assignment->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']))
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Store Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateGuruMapelRequest $request, GuruMapel $guruMapel): JsonResponse
    {
        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $validated['tahun_ajaran_id'] = $validated['tahun_ajaran_id'] ?? optional($tahunAktif)->id;

        $jamMulai = JamSekolah::find($validated['jam_mulai_id']);
        $jamSelesai = JamSekolah::find($validated['jam_selesai_id']);

        if (!$jamMulai || !$jamSelesai) {
            return response()->json(['success' => false, 'message' => 'Data jam tidak valid.'], 422);
        }

        if ($jamMulai->hari !== $validated['hari'] || $jamSelesai->hari !== $validated['hari']) {
            return response()->json([
                'success' => false, 
                'message' => "Gagal: Jam yang dipilih tidak tersedia pada hari {$validated['hari']}."
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($jamSelesai->waktu_selesai <= $jamMulai->waktu_mulai) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Jam selesai harus lebih besar dari jam mulai.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $bentrok = GuruMapel::where('id', '<>', $guruMapel->id)
            ->where('hari', $validated['hari'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->where(function ($q) use ($jamMulai, $jamSelesai) {
                $q->whereHas('jamMulai', function ($query) use ($jamSelesai) {
                    $query->where('waktu_mulai', '<', $jamSelesai->waktu_selesai);
                })->whereHas('jamSelesai', function ($query) use ($jamMulai) {
                    $query->where('waktu_selesai', '>', $jamMulai->waktu_mulai);
                });
            })
            ->where(function ($q) use ($validated) {
                $q->where('guru_staf_id', $validated['guru_staf_id'])
                  ->orWhere('kelas_id', $validated['kelas_id']);
            })
            ->first();

        if ($bentrok) {
            $type = $bentrok->guru_staf_id == $validated['guru_staf_id'] ? "Guru" : "Kelas";
            return response()->json([
                'success' => false, 
                'message' => "Jadwal Bentrok: {$type} sudah memiliki jadwal lain di jam tersebut."
            ], Response::HTTP_CONFLICT);
        }

        $exists = GuruMapel::where('guru_staf_id', $validated['guru_staf_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->where('kelas_id', $validated['kelas_id'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->where('id', '<>', $guruMapel->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Data serupa sudah ada di sistem.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            DB::transaction(fn() => $guruMapel->update($validated));
            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru berhasil diperbarui.',
                'data'    => new GuruMapelResource($guruMapel->refresh()->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran']))
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Update Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(GuruMapel $guruMapel): JsonResponse
    {
        try {
            DB::transaction(fn() => $guruMapel->delete());
            return response()->json(['success' => true, 'message' => 'Berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}