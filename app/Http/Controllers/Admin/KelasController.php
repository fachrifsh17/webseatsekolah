<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Kelas, Siswa, Jurusan, TahunAjaran, ProfilSekolah, DataKontak};
use App\Http\Requests\{StoreKelasRequest, UpdateKelasRequest};
use App\Http\Resources\KelasResource;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Log};
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\KelasExport;
use App\Imports\KelasImport;

class KelasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.aktivitas')->only([
            'store', 
            'update', 
            'destroy', 
            'import', 
            'generateFromPreviousYear'
        ]);
        
        // Injeksi Policy untuk CRUD standar
        $this->authorizeResource(Kelas::class, 'kelas');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Kelas::with(['jurusan', 'tahunAjaran', 'waliKelas'])
                ->withCount('siswa');

            if ($request->filled('search')) {
                $query->where('nama_kelas', 'like', '%' . $request->search . '%');
            }

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            } else {
                $query->whereHas('tahunAjaran', function($q) {
                    $q->where('is_active', true);
                });
            }

            $filters = ['jurusan_id', 'wali_kelas_id'];
            foreach ($filters as $filter) {
                if ($request->filled($filter)) {
                    $query->where($filter, $request->{$filter});
                }
            }

            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            } else {
                $query->where('is_active', true);
            }

            $perPage = $request->get('per_page', 10);
            $kelas = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => KelasResource::collection($kelas),
                'meta'    => [
                    'current_page' => $kelas->currentPage(),
                    'last_page'    => $kelas->lastPage(),
                    'per_page'     => $kelas->perPage(),
                    'total'        => $kelas->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch kelas', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export(Request $request)
    {
        // Otorisasi Manual untuk custom method
        $this->authorize('viewAny', Kelas::class);

        try {
            $filters = $request->only(['search', 'jurusan_id', 'wali_kelas_id', 'tahun_ajaran_id']);
            $filters['is_active'] = true;

            $profil = ProfilSekolah::first() ?? new ProfilSekolah(); 
            $kontak = DataKontak::first() ?? new DataKontak(); 

            $fileNameParts = ['data_kelas'];

            if ($request->filled('jurusan_id')) {
                $jurusan = Jurusan::find($request->jurusan_id);
                if ($jurusan) {
                    $fileNameParts[] = str_replace(' ', '-', strtolower($jurusan->nama_jurusan));
                }
            }

            if ($request->filled('tahun_ajaran_id')) {
                $ta = TahunAjaran::find($request->tahun_ajaran_id);
            } else {
                $ta = TahunAjaran::where('is_active', true)->first();
                if ($ta) {
                    $filters['tahun_ajaran_id'] = $ta->id;
                }
            }

            if (isset($ta)) {
                $taName = str_replace(['/', ' '], '-', $ta->nama);
                $semester = strtolower($ta->semester);
                $fileNameParts[] = "{$taName}_{$semester}";
            }

            $fileName = implode('_', $fileNameParts) . '_' . date('Ymd_His') . '.xlsx';

            return Excel::download(new KelasExport($filters, $profil, $kontak), $fileName);
        } catch (Throwable $e) {
            Log::error('Export Kelas Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', Kelas::class);

        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            $import = new KelasImport;
            Excel::import($import, $request->file('file'));
            
            $skippedMessages = $import->getMessages();

            return response()->json([
                'success' => true,
                'message' => 'Proses impor selesai.',
                'info'    => $skippedMessages, 
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Import Kelas Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function generateFromPreviousYear(): JsonResponse
    {
        $this->authorize('create', Kelas::class);

        set_time_limit(300);

        try {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            if (!$tahunAktif) {
                return response()->json(['success' => false, 'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif.'], Response::HTTP_BAD_REQUEST);
            }

            $isGenap = strtolower($tahunAktif->semester) === 'genap';
            $tahunSumber = $isGenap 
                ? TahunAjaran::where('nama', $tahunAktif->nama)->where('semester', 'Ganjil')->first()
                : TahunAjaran::where('id', '<', $tahunAktif->id)->orderBy('id', 'desc')->first();

            if (!$tahunSumber) {
                return response()->json(['success' => false, 'message' => 'Gagal: Data periode sumber tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $kelasLama = Kelas::where('tahun_ajaran_id', $tahunSumber->id)->where('is_active', true)->get();
            if ($kelasLama->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Gagal: Tidak ada data kelas aktif di periode sebelumnya.'], Response::HTTP_NOT_FOUND);
            }

            $countKelas = 0;
            $countSiswa = 0;

            DB::transaction(function () use ($kelasLama, $tahunAktif, $isGenap, &$countKelas, &$countSiswa) {
                foreach ($kelasLama as $item) {
                    $kelasBaru = Kelas::where('nama_kelas', $item->nama_kelas)
                        ->where('tahun_ajaran_id', $tahunAktif->id)
                        ->first();

                    if (!$kelasBaru) {
                        $waliId = $item->wali_kelas_id;
                        if ($waliId && Kelas::where('tahun_ajaran_id', $tahunAktif->id)->where('wali_kelas_id', $waliId)->exists()) {
                            $waliId = null;
                        }

                        $kelasBaru = Kelas::create([
                            'nama_kelas'      => $item->nama_kelas,
                            'jurusan_id'      => $item->jurusan_id,
                            'tahun_ajaran_id' => $tahunAktif->id,
                            'is_active'       => true,
                            'wali_kelas_id'   => $isGenap ? $waliId : null,
                        ]);
                        $countKelas++;
                    }

                    if ($isGenap) {
                        $countSiswa += Siswa::where('kelas_id', $item->id)
                            ->where('is_active', true)
                            ->update(['kelas_id' => $kelasBaru->id]);
                    }
                }
            });

            $msg = $isGenap 
                ? "Berhasil menyalin {$countKelas} kelas dan memindahkan {$countSiswa} siswa ke Semester Genap."
                : "Berhasil menyalin {$countKelas} data kelas ke tahun ajaran baru.";

            return response()->json(['success' => true, 'message' => $msg], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('Failed to generate kelas', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false, 
                'message' => 'Gagal memproses data periode.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreKelasRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif) {
            return response()->json(['success' => false, 'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif.'], Response::HTTP_BAD_REQUEST);
        }

        $validated['tahun_ajaran_id'] = $tahunAktif->id;
        $validated['is_active'] = true;

        if (!empty($validated['wali_kelas_id'])) {
            if (Kelas::where('wali_kelas_id', $validated['wali_kelas_id'])->where('tahun_ajaran_id', $tahunAktif->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Conflict: Guru tersebut sudah menjadi wali kelas di tahun ajaran aktif.'], Response::HTTP_CONFLICT);
            }
        }

        if (Kelas::where('nama_kelas', $validated['nama_kelas'])->where('tahun_ajaran_id', $tahunAktif->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Conflict: Nama kelas sudah terdaftar di tahun ajaran aktif.'], Response::HTTP_CONFLICT);
        }

        try {
            $kelas = DB::transaction(fn() => Kelas::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil ditambahkan.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])->loadCount('siswa')),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create kelas', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Kelas $kelas): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])->loadCount('siswa')),
        ], Response::HTTP_OK);
    }

    public function update(UpdateKelasRequest $request, Kelas $kelas): JsonResponse
    {
        $validated = $request->validated();
        $waliId = $validated['wali_kelas_id'] ?? $kelas->wali_kelas_id;

        if (!empty($waliId)) {
            if (Kelas::where('wali_kelas_id', $waliId)->where('tahun_ajaran_id', $kelas->tahun_ajaran_id)->where('id', '!=', $kelas->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Conflict: Guru tersebut sudah menjadi wali kelas di periode ini.'], Response::HTTP_CONFLICT);
            }
        }

        if (!empty($validated['nama_kelas'])) {
            if (Kelas::where('nama_kelas', $validated['nama_kelas'])->where('tahun_ajaran_id', $kelas->tahun_ajaran_id)->where('id', '!=', $kelas->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Conflict: Nama kelas sudah terdaftar di periode ini.'], Response::HTTP_CONFLICT);
            }
        }

        try {
            DB::transaction(fn() => $kelas->update($validated));
            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil diperbarui.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])->loadCount('siswa')),
            ], Response::HTTP_OK);
        } catch (Throwable) {
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        try {
            if ($kelas->siswa()->exists()) {
                return response()->json(['success' => false, 'message' => 'Gagal: Kelas tidak bisa dihapus karena masih memiliki data siswa.'], Response::HTTP_CONFLICT);
            }

            DB::transaction(fn() => $kelas->delete());
            return response()->json(['success' => true, 'message' => 'Data kelas berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}