<?php

namespace App\Http\Controllers\KetuaJurusan;

use App\Http\Controllers\Controller;
use App\Models\{GuruMapel, JamSekolah, TahunAjaran, GuruStaf, MataPelajaran, Kelas, ProfilSekolah, DataKontak};
use App\Http\Resources\GuruMapelResource;
use App\Http\Requests\{StoreGuruMapelRequest, UpdateGuruMapelRequest};
use App\Exports\GuruMapelExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{DB, Log, Auth};
use Illuminate\Support\Str;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class GuruMapelController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['store', 'update', 'destroy']);
    }

    private function getJurusanId()
    {
        return Auth::user()->guruStaf->jurusan_id ?? null;
    }

    private function getNamaJurusan()
    {
        return Auth::user()->guruStaf->jurusan->nama_jurusan ?? 'Jurusan';
    }

    private function applyFilters(Request $request)
    {
        $jurusanId = $this->getJurusanId();

        $query = GuruMapel::with(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran', 'jamMulai', 'jamSelesai'])
            ->whereHas('kelas', function ($q) use ($jurusanId) {
                $q->where('jurusan_id', $jurusanId);
            });

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
        $perPage = (int) $request->get('per_page', 10);
        $assignments = $query->paginate($perPage);
        
        $paginationData = $assignments->toArray();

        return response()->json([
            'success' => true,
            'data'    => GuruMapelResource::collection($assignments),
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
            ],
        ], Response::HTTP_OK);
    }

    public function export(Request $request)
    {
        try {
            $query = $this->applyFilters($request);
            $namaJurusan = $this->getNamaJurusan();
            $tahunAktif = TahunAjaran::where('is_active', 1)->first();
            
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
                $tahun = $tahunAktif;
            }

            if ($tahun) {
                $filters['tahun_ajaran'] = $tahun->nama;
                $filters['semester'] = $tahun->semester; 
                $taClean = str_replace(['/', ' '], '_', $tahun->nama);
                $nameParts[] = $taClean . '_' . strtoupper($tahun->semester);
            }

            if ($namaJurusan) {
                $nameParts[] = strtoupper(Str::slug($namaJurusan, '_'));
            }

            if ($request->filled('guru_staf_id')) {
                $guru = GuruStaf::find($request->guru_staf_id);
                if ($guru) {
                    $filters['guru'] = $guru->nama;
                    $nameParts[] = strtoupper(Str::slug($guru->nama, '_'));
                }
            }

            if ($request->filled('mata_pelajaran_id')) {
                $mapel = MataPelajaran::find($request->mata_pelajaran_id);
                if ($mapel) {
                    $filters['mapel'] = $mapel->nama_mapel;
                    $nameParts[] = strtoupper(Str::slug($mapel->nama_mapel, '_'));
                }
            }

            if ($request->filled('kelas_id')) {
                $kelas = Kelas::find($request->kelas_id);
                if ($kelas) {
                    $filters['kelas'] = $kelas->nama_kelas;
                    $nameParts[] = strtoupper(Str::slug($kelas->nama_kelas, '_'));
                }
            }

            if ($request->filled('hari')) {
                $nameParts[] = strtoupper($request->hari);
            }

            if ($request->filled('q')) {
                $nameParts[] = 'SEARCH_' . strtoupper(Str::slug($request->q, '_'));
            }

            $nameParts[] = 'AKTIF';

            $profil = ProfilSekolah::first() ?? new ProfilSekolah();
            $kontak = DataKontak::first() ?? new DataKontak();

            $fileName = implode('_', $nameParts) . '.xlsx';
            
            if (ob_get_contents()) ob_end_clean();

            return Excel::download(new GuruMapelExport($query, $profil, $kontak, $filters), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Gagal mengekspor data penugasan.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(GuruMapel $guruMapel): JsonResponse
    {
        // TAMBAHAN: Otorisasi via Policy
        $this->authorize('view', $guruMapel);

        if ($guruMapel->kelas->jurusan_id !== $this->getJurusanId()) {
            return response()->json(['message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'success' => true,
            'data'    => new GuruMapelResource($guruMapel->load(['guru', 'mapel.jurusan', 'kelas', 'tahunAjaran', 'jamMulai', 'jamSelesai']))
        ], Response::HTTP_OK);
    }

    public function getJamByHari(Request $request): JsonResponse
    {
        $hari = $request->query('hari');
        $jam = JamSekolah::where('hari', $hari)->orderBy('waktu_mulai')->get();

        return response()->json(['success' => true, 'data' => $jam], Response::HTTP_OK);
    }

    public function store(StoreGuruMapelRequest $request): JsonResponse
    {
        // TAMBAHAN: Otorisasi via Policy (Memastikan user boleh membuat data baru)
        $this->authorize('create', GuruMapel::class);

        $validated = $request->validated();
        $jurusanId = $this->getJurusanId();
        
        $kelas = Kelas::find($validated['kelas_id']);
        if (!$kelas || $kelas->jurusan_id !== $jurusanId) {
            return response()->json(['success' => false, 'message' => 'Gagal: Kelas tidak sesuai jurusan Anda.'], Response::HTTP_FORBIDDEN);
        }

        $mapel = MataPelajaran::find($validated['mata_pelajaran_id']);
        if (!$mapel || $mapel->is_active == 0) {
            return response()->json(['success' => false, 'message' => 'Gagal: Mata pelajaran tidak valid.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $validated['tahun_ajaran_id'] = $validated['tahun_ajaran_id'] ?? optional($tahunAktif)->id;

        $jamMulai = JamSekolah::find($validated['jam_mulai_id']);
        $jamSelesai = JamSekolah::find($validated['jam_selesai_id']);

        if (!$jamMulai || !$jamSelesai) {
            return response()->json(['success' => false, 'message' => 'Data jam tidak valid.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($jamMulai->hari !== $validated['hari'] || $jamSelesai->hari !== $validated['hari']) {
            return response()->json(['success' => false, 'message' => "Gagal: Jam tidak tersedia pada hari {$validated['hari']}."], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($jamSelesai->waktu_selesai <= $jamMulai->waktu_mulai) {
            return response()->json(['success' => false, 'message' => 'Gagal: Jam selesai harus lebih besar dari jam mulai.'], Response::HTTP_UNPROCESSABLE_ENTITY);
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
            return response()->json(['success' => false, 'message' => "Jadwal Bentrok: {$type} sudah memiliki jadwal lain."], Response::HTTP_CONFLICT);
        }

        $exists = GuruMapel::where('guru_staf_id', $validated['guru_staf_id'])
            ->where('mata_pelajaran_id', $validated['mata_pelajaran_id'])
            ->where('kelas_id', $validated['kelas_id'])
            ->where('tahun_ajaran_id', $validated['tahun_ajaran_id'])
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Data penugasan sudah ada.'], Response::HTTP_CONFLICT);
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
        // TAMBAHAN: Otorisasi via Policy
        $this->authorize('update', $guruMapel);

        $jurusanId = $this->getJurusanId();
        if ($guruMapel->kelas->jurusan_id !== $jurusanId) {
            return response()->json(['message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', 1)->first();
        $validated['tahun_ajaran_id'] = $validated['tahun_ajaran_id'] ?? optional($tahunAktif)->id;

        $jamMulai = JamSekolah::find($validated['jam_mulai_id']);
        $jamSelesai = JamSekolah::find($validated['jam_selesai_id']);

        if (!$jamMulai || !$jamSelesai) {
            return response()->json(['success' => false, 'message' => 'Data jam tidak valid.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($jamMulai->hari !== $validated['hari'] || $jamSelesai->hari !== $validated['hari']) {
            return response()->json(['success' => false, 'message' => "Gagal: Jam tidak tersedia pada hari {$validated['hari']}."], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($jamSelesai->waktu_selesai <= $jamMulai->waktu_mulai) {
            return response()->json(['success' => false, 'message' => 'Gagal: Jam selesai harus lebih besar dari jam mulai.'], Response::HTTP_UNPROCESSABLE_ENTITY);
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
            return response()->json(['success' => false, 'message' => "Jadwal Bentrok: {$type} sudah memiliki jadwal lain."], Response::HTTP_CONFLICT);
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
        // TAMBAHAN: Otorisasi via Policy
        $this->authorize('delete', $guruMapel);

        if ($guruMapel->kelas->jurusan_id !== $this->getJurusanId()) {
            return response()->json(['message' => 'Akses ditolak.'], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::transaction(fn() => $guruMapel->delete());
            return response()->json(['success' => true, 'message' => 'Berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Delete Guru Mapel Error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}