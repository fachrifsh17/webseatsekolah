<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Jurusan;
use App\Models\TahunAjaran;
use App\Models\ProfilSekolah;
use App\Models\DataKontak; 
use App\Http\Requests\StoreKelasRequest;
use App\Http\Requests\UpdateKelasRequest;
use App\Http\Resources\KelasResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\KelasExport;
use App\Imports\KelasImport;

class KelasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Kelas::with(['jurusan', 'tahunAjaran', 'waliKelas']);

            if ($request->filled('search')) {
                $query->where('nama_kelas', 'like', '%' . $request->search . '%');
            }

            if ($request->filled('jurusan_id')) {
                $query->where('jurusan_id', $request->jurusan_id);
            }

            if ($request->filled('wali_kelas_id')) {
                $query->where('wali_kelas_id', $request->wali_kelas_id);
            }

            if ($request->filled('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            $kelas = $query->latest()->get();

            return response()->json([
                'success' => true,
                'data'    => KelasResource::collection($kelas),
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
        try {
            $filters = $request->only([
                'search', 
                'jurusan_id', 
                'wali_kelas_id', 
                'tahun_ajaran_id'
            ]);
            
            // Memaksa filter hanya untuk data yang aktif
            $filters['is_active'] = 1;
            
            $profil = ProfilSekolah::first() ?? new ProfilSekolah(); 
            $kontak = DataKontak::first() ?? new DataKontak(); 

            $fileNameParts = ['data_kelas_aktif'];

            if ($request->filled('jurusan_id')) {
                $jurusan = Jurusan::find($request->jurusan_id);
                if ($jurusan) {
                    $fileNameParts[] = str_replace(' ', '_', strtolower($jurusan->nama_jurusan));
                }
            }

            if ($request->filled('tahun_ajaran_id')) {
                $ta = TahunAjaran::find($request->tahun_ajaran_id);
                if ($ta) {
                    $fileNameParts[] = str_replace('/', '-', $ta->tahun_ajaran);
                }
            }

            $fileNameParts[] = date('Ymd_His');
            $fileName = implode('_', $fileNameParts) . '.xlsx';

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
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            Excel::import(new KelasImport, $request->file('file'));
            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil diimpor.',
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
        try {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();
            if (!$tahunAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $tahunSebelumnya = TahunAjaran::where('id', '<', $tahunAktif->id)
                ->orderBy('id', 'desc')
                ->first();

            if (!$tahunSebelumnya) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal: Data tahun ajaran sebelumnya tidak ditemukan.',
                ], Response::HTTP_NOT_FOUND);
            }

            $kelasLama = Kelas::where('tahun_ajaran_id', $tahunSebelumnya->id)->get();
            
            if ($kelasLama->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal: Tidak ada data kelas di tahun ajaran sebelumnya.',
                ], Response::HTTP_NOT_FOUND);
            }

            $count = 0;
            DB::transaction(function () use ($kelasLama, $tahunAktif, &$count) {
                foreach ($kelasLama as $item) {
                    $exists = Kelas::where('nama_kelas', $item->nama_kelas)
                        ->where('tahun_ajaran_id', $tahunAktif->id)
                        ->exists();

                    if (!$exists) {
                        Kelas::create([
                            'nama_kelas'      => $item->nama_kelas,
                            'jurusan_id'      => $item->jurusan_id,
                            'tahun_ajaran_id' => $tahunAktif->id,
                            'is_active'       => true,
                        ]);
                        $count++;
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => "Berhasil menyalin {$count} data kelas ke tahun ajaran baru.",
            ], Response::HTTP_CREATED);

        } catch (Throwable $e) {
            Log::error('Failed to generate kelas', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyalin data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreKelasRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Tidak ada Tahun Ajaran yang aktif.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated['tahun_ajaran_id'] = $tahunAktif->id;

        if (!empty($validated['wali_kelas_id'])) {
            $existsWali = Kelas::where('wali_kelas_id', $validated['wali_kelas_id'])
                ->where('tahun_ajaran_id', $tahunAktif->id)
                ->exists();

            if ($existsWali) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict: Guru tersebut sudah menjadi wali kelas di tahun ajaran aktif.',
                ], Response::HTTP_CONFLICT);
            }
        }

        if (!empty($validated['nama_kelas'])) {
            $existsNama = Kelas::where('nama_kelas', $validated['nama_kelas'])
                ->where('tahun_ajaran_id', $tahunAktif->id)
                ->exists();

            if ($existsNama) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict: Nama kelas sudah terdaftar di tahun ajaran aktif.',
                ], Response::HTTP_CONFLICT);
            }
        }

        try {
            $kelas = DB::transaction(function () use ($validated) {
                return Kelas::create($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil ditambahkan.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])),
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('Failed to create kelas', ['payload' => $validated, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Kelas $kelas): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to fetch kelas detail', ['kelas_id' => $kelas->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateKelasRequest $request, Kelas $kelas): JsonResponse
    {
        $validated = $request->validated();
        $waliId = $validated['wali_kelas_id'] ?? $kelas->wali_kelas_id;

        if (!empty($waliId)) {
            $existsWali = Kelas::where('wali_kelas_id', $waliId)
                ->where('tahun_ajaran_id', $kelas->tahun_ajaran_id)
                ->where('id', '!=', $kelas->id)
                ->exists();

            if ($existsWali) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict: Guru tersebut sudah menjadi wali kelas di periode ini.',
                ], Response::HTTP_CONFLICT);
            }
        }

        if (!empty($validated['nama_kelas'])) {
            $existsNama = Kelas::where('nama_kelas', $validated['nama_kelas'])
                ->where('tahun_ajaran_id', $kelas->tahun_ajaran_id)
                ->where('id', '!=', $kelas->id)
                ->exists();

            if ($existsNama) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict: Nama kelas sudah terdaftar di periode ini.',
                ], Response::HTTP_CONFLICT);
            }
        }

        try {
            DB::transaction(function () use ($kelas, $validated) {
                $kelas->update($validated);
            });

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil diperbarui.',
                'data'    => new KelasResource($kelas->load(['jurusan', 'tahunAjaran', 'waliKelas'])),
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to update kelas', ['kelas_id' => $kelas->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        try {
            if ($kelas->siswa()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal: Kelas tidak bisa dihapus karena masih memiliki data siswa.',
                ], Response::HTTP_CONFLICT);
            }

            DB::transaction(function () use ($kelas) {
                $kelas->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Data kelas berhasil dihapus.',
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            Log::error('Failed to delete kelas', ['kelas_id' => $kelas->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}