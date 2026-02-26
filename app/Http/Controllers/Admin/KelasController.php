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
        
        $this->authorizeResource(Kelas::class, 'kelas');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            $taId = $request->get('tahun_ajaran_id', $tahunAktif?->id);

            $query = Kelas::with(['jurusan', 'waliKelas'])
                ->withCount(['siswa as siswa_count' => function($q) use ($taId) {
                    $q->where('siswa_kelas.is_active', true);
                    if ($taId) {
                        $q->where('siswa_kelas.tahun_ajaran_id', $taId);
                    }
                }]);

            if ($request->filled('search')) {
                $query->where('nama_kelas', 'like', '%' . $request->search . '%');
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
            $kelas = $query->orderBy('nama_kelas', 'asc')->paginate($perPage);
            $paginationData = $kelas->toArray();

            return response()->json([
                'success' => true,
                'data'    => KelasResource::collection($kelas),
                'meta'    => [
                    'current_page'  => $kelas->currentPage(),
                    'last_page'     => $kelas->lastPage(),
                    'per_page'      => $kelas->perPage(),
                    'total'         => $kelas->total(),
                    'from'          => $kelas->firstItem(),
                    'to'            => $kelas->lastItem(),
                    'next_page_url' => $kelas->nextPageUrl(),
                    'prev_page_url' => $kelas->previousPageUrl(),
                    'path'          => $paginationData['path'],
                    'links'         => $paginationData['links'],
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
        $this->authorize('viewAny', Kelas::class);

        try {
            $filters = $request->only(['search', 'jurusan_id', 'wali_kelas_id', 'tahun_ajaran_id']);
            $filters['is_active'] = true;
            $filters['identitas_laporan'] = 'SEMUA JURUSAN';

            $profil = ProfilSekolah::first() ?? new ProfilSekolah(); 
            $kontak = DataKontak::first() ?? new DataKontak(); 

            $fileNameParts = ['DATA_KELAS'];

            if ($request->filled('search')) {
                $fileNameParts[] = strtoupper(str_replace(' ', '_', $request->search));
            }

            if ($request->filled('jurusan_id')) {
                $jurusan = Jurusan::find($request->jurusan_id);
                if ($jurusan) {
                    $filters['identitas_laporan'] = 'JURUSAN ' . strtoupper($jurusan->nama_jurusan);
                    $fileNameParts[] = strtoupper(str_replace([' ', '-'], '_', $jurusan->nama_jurusan));
                }
            }

            $ta = $request->filled('tahun_ajaran_id') 
                ? TahunAjaran::find($request->tahun_ajaran_id) 
                : TahunAjaran::where('is_active', true)->first();

            if ($ta) {
                $filters['tahun_ajaran_id'] = $ta->id;
                $taName = str_replace(['/', ' '], '_', $ta->nama);
                $semester = strtoupper($ta->semester);
                $fileNameParts[] = "{$taName}_{$semester}";
            }

            $fileNameParts[] = 'AKTIF';
            $fileName = implode('_', $fileNameParts) . '.xlsx';

            if (ob_get_contents()) ob_end_clean();

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
            
            if (!$tahunAktif || strtolower($tahunAktif->semester) !== 'genap') {
                return response()->json([
                    'success' => false, 
                    'message' => 'Gagal: Fitur ini hanya tersedia saat Tahun Ajaran aktif berada di Semester Genap.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $tahunSumber = TahunAjaran::where('nama', $tahunAktif->nama)
                ->where('semester', 'Ganjil')
                ->first();

            if (!$tahunSumber) {
                return response()->json(['success' => false, 'message' => 'Gagal: Data Semester Ganjil tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $siswaGanjil = DB::table('siswa_kelas')
                ->where('tahun_ajaran_id', $tahunSumber->id)
                ->where('is_active', true)
                ->get();

            if ($siswaGanjil->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Gagal: Tidak ada siswa aktif di Semester Ganjil.'], Response::HTTP_NOT_FOUND);
            }

            $countSiswa = 0;

            DB::transaction(function () use ($siswaGanjil, $tahunAktif, &$countSiswa) {
                foreach ($siswaGanjil as $item) {
                    $exists = DB::table('siswa_kelas')
                        ->where('siswa_id', $item->siswa_id)
                        ->where('tahun_ajaran_id', $tahunAktif->id)
                        ->exists();

                    if (!$exists) {
                        DB::table('siswa_kelas')->insert([
                            'siswa_id'        => $item->siswa_id,
                            'kelas_id'        => $item->kelas_id,
                            'tahun_ajaran_id' => $tahunAktif->id,
                            'is_active'       => true,
                            'created_at'      => now(),
                            'updated_at'      => now()
                        ]);
                        $countSiswa++;
                    }
                }
            });

            return response()->json([
                'success' => true, 
                'message' => "Berhasil memindahkan {$countSiswa} siswa ke Semester Genap."
            ], Response::HTTP_CREATED);

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

        if (!empty($validated['jurusan_id'])) {
            $jurusanAktif = Jurusan::where('id', $validated['jurusan_id'])->where('is_active', 1)->exists();
            if (!$jurusanAktif) {
                return response()->json(['success' => false, 'message' => 'Gagal: Jurusan tidak aktif.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (Kelas::where('nama_kelas', $validated['nama_kelas'])->exists()) {
            return response()->json(['success' => false, 'message' => 'Conflict: Nama kelas sudah terdaftar.'], Response::HTTP_CONFLICT);
        }

        $validated['is_active'] = true;

        try {
            $kelas = DB::transaction(fn() => Kelas::create($validated));
            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil ditambahkan.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'waliKelas'])->loadCount(['siswa as siswa_count' => fn($q) => $q->where('siswa_kelas.is_active', true)])),
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
            'data'    => new KelasResource($kelas->load(['jurusan', 'waliKelas'])->loadCount(['siswa as siswa_count' => fn($q) => $q->where('siswa_kelas.is_active', true)])),
        ], Response::HTTP_OK);
    }

    public function update(UpdateKelasRequest $request, Kelas $kelas): JsonResponse
    {
        $validated = $request->validated();

        if (!empty($validated['jurusan_id'])) {
            $jurusanAktif = Jurusan::where('id', $validated['jurusan_id'])->where('is_active', 1)->exists();
            if (!$jurusanAktif) {
                return response()->json(['success' => false, 'message' => 'Gagal: Jurusan tidak aktif.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (!empty($validated['nama_kelas'])) {
            if (Kelas::where('nama_kelas', $validated['nama_kelas'])->where('id', '!=', $kelas->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Conflict: Nama kelas sudah digunakan.'], Response::HTTP_CONFLICT);
            }
        }

        if ($request->has('is_active')) {
            $validated['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        }

        try {
            DB::transaction(fn() => $kelas->update($validated));
            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil diperbarui.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'waliKelas'])->loadCount(['siswa as siswa_count' => fn($q) => $q->where('siswa_kelas.is_active', true)])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        try {
            if (DB::table('siswa_kelas')->where('kelas_id', $kelas->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'Gagal: Kelas memiliki riwayat data siswa.'], Response::HTTP_CONFLICT);
            }

            DB::transaction(fn() => $kelas->delete());
            return response()->json(['success' => true, 'message' => 'Data kelas berhasil dihapus.'], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data kelas.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}