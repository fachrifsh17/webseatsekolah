<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, User, Semester, Orangtua, TahunAjaran};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log, Validator};
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class KenaikanKelasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        // Tambahkan 'index' di sini jika ingin log aktivitas untuk melihat riwayat
        $this->middleware('log.aktivitas')->only(['prosesMassal']);
    }

    /**
     * Menampilkan riwayat siswa berdasarkan kelas dan semester tertentu.
     * Menggunakan nama metode 'index' sesuai permintaan.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'kelas_id'    => 'required|exists:kelas,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $kelasId = $request->input('kelas_id');
        $semesterId = $request->input('semester_id');

        // Mengambil data siswa yang pernah terdaftar di kelas dan semester tersebut
        $riwayat = DB::table('siswa_kelas')
            ->join('siswa', 'siswa_kelas.siswa_id', '=', 'siswa.id')
            ->where('siswa_kelas.kelas_id', $kelasId)
            ->where('siswa_kelas.semester_id', $semesterId)
            ->select(
                'siswa.id',
                'siswa.nama_lengkap',
                'siswa.nis',
                'siswa_kelas.is_active as status_di_kelas'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $riwayat
        ], Response::HTTP_OK);
    }

    public function prosesMassal(Request $request): JsonResponse
    {
        $this->authorize('create', Kelas::class);

        $validator = Validator::make($request->all(), [
            'mapping' => 'required|array',
            'mapping.*.kelas_lama_id' => 'required|exists:kelas,id',
            'mapping.*.kelas_baru_id' => 'nullable|exists:kelas,id',
            'mapping.*.excluded_siswa_ids' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated = $validator->validated();
        
        // 1. Ambil Semester Aktif (Tujuan) dan Tahun Ajarannya
        $semesterAktif = Semester::with('tahunAjaran')->where('is_active', true)->first();
        
        if (!$semesterAktif) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Tidak ada semester yang aktif.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // --- VALIDASI: HARUS SEMESTER GANJIL ---
        if (!str_contains(strtolower($semesterAktif->nama), 'ganjil')) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Proses kenaikan kelas hanya bisa dilakukan di semester GANJIL (awal tahun ajaran).'
            ], Response::HTTP_BAD_REQUEST);
        }
        // ----------------------------------------

        // 2. Ambil Semester Sebelumnya (Sumber)
        $semesterLama = Semester::where('id', '<', $semesterAktif->id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$semesterLama) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Tidak ditemukan data semester sebelumnya.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // --- VALIDASI: PASTIKAN TAHUN AJARAN BERBEDA ---
        if ($semesterAktif->tahun_ajaran_id === $semesterLama->tahun_ajaran_id) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Semester aktif dan semester sebelumnya berada dalam tahun ajaran yang sama.'
            ], Response::HTTP_BAD_REQUEST);
        }
        // ------------------------------------------------

        $summary = [
            'berhasil_naik' => 0,
            'lulus'         => 0,
            'peringatan'    => []
        ];

        try {
            DB::transaction(function () use ($validated, $semesterAktif, $semesterLama, &$summary) {
                foreach ($validated['mapping'] as $map) {
                    $kelasLama = Kelas::with('tingkatan')->find($map['kelas_lama_id']);
                    if (!$kelasLama) continue;

                    $excludedIds = $map['excluded_siswa_ids'] ?? [];

                    // Ambil siswa dari semester lama
                    $siswaIds = DB::table('siswa_kelas')
                        ->where('kelas_id', $kelasLama->id)
                        ->where('semester_id', $semesterLama->id)
                        ->where('is_active', true)
                        ->pluck('siswa_id');

                    $targetSiswaIds = $siswaIds->diff($excludedIds);

                    if (empty($map['kelas_baru_id'])) {
                        // --- SKENARIO KELULUSAN ---
                        $isKelasAkhir = str_contains(strtoupper($kelasLama->tingkatan->nama_tingkatan), 'XII') || 
                                        str_contains($kelasLama->tingkatan->nama_tingkatan, '12');
                        
                        if (!$isKelasAkhir) {
                            $summary['peringatan'][] = "Gagal Lulus: Kelas {$kelasLama->nama_kelas} bukan tingkat akhir.";
                            continue;
                        }

                        foreach ($targetSiswaIds as $sId) {
                            // Non-aktifkan riwayat kelas lama
                            DB::table('siswa_kelas')
                                ->where('siswa_id', $sId)
                                ->where('kelas_id', $kelasLama->id)
                                ->where('semester_id', $semesterLama->id)
                                ->update(['is_active' => true]);

                            // Non-aktifkan Siswa di Master
                            $siswa = Siswa::find($sId);
                            if ($siswa) {
                                $siswa->update(['is_active' => false]);
                                
                                if ($siswa->user_id) {
                                    User::where('id', $siswa->user_id)->update(['is_active' => false]);
                                }

                                // Cek & Non-aktifkan Orang Tua
                                $orangTuas = $siswa->orangtua; 

                                foreach ($orangTuas as $ot) {
                                    $checkAnakLain = Siswa::whereHas('orangtua', function($query) use ($ot) {
                                            $query->where('orangtua.id', $ot->id);
                                        })
                                        ->where('id', '!=', $siswa->id)
                                        ->where('is_active', true) 
                                        ->exists();

                                    if (!$checkAnakLain) {
                                        DB::table('orangtua')->where('id', $ot->id)->update(['is_active' => false]);
                                        if ($ot->user_id) {
                                            User::where('id', $ot->user_id)->update(['is_active' => false]);
                                        }
                                    }
                                }
                            }
                            $summary['lulus']++;
                        }
                    } else {
                        // --- SKENARIO NAIK / PINDAH KELAS ---
                        $kelasBaru = Kelas::with('tingkatan')->find($map['kelas_baru_id']);

                        $isKelasAkhirLama = str_contains(strtoupper($kelasLama->tingkatan->nama_tingkatan), 'XII') || 
                                            str_contains($kelasLama->tingkatan->nama_tingkatan, '12');
                        
                        if ($isKelasAkhirLama) {
                            $summary['peringatan'][] = "Gagal Naik: Siswa kelas XII ({$kelasLama->nama_kelas}) tidak boleh pindah kelas, harus lulus.";
                            continue;
                        }

                        // Validasi Lompat Tingkat
                        if ($kelasBaru->tingkatan_id > ($kelasLama->tingkatan_id + 1)) {
                            $summary['peringatan'][] = "Gagal Naik: Tidak boleh lompat tingkat dari {$kelasLama->tingkatan->nama_tingkatan} ke {$kelasBaru->tingkatan->nama_tingkatan}.";
                            continue;
                        }

                        if (!$kelasBaru || $kelasLama->jurusan_id !== $kelasBaru->jurusan_id) {
                            $summary['peringatan'][] = "Jurusan tidak cocok: {$kelasLama->nama_kelas} -> {$kelasBaru->nama_kelas}.";
                            continue;
                        }
                        
                        foreach ($targetSiswaIds as $sId) {
                            // Non-aktifkan riwayat kelas lama
                            DB::table('siswa_kelas')
                                ->where('siswa_id', $sId)
                                ->where('kelas_id', $kelasLama->id)
                                ->where('semester_id', $semesterLama->id)
                                ->update(['is_active' => true]); 

                            // Masukkan ke kelas baru
                            DB::table('siswa_kelas')->insert([
                                'siswa_id'        => $sId,
                                'kelas_id'        => $kelasBaru->id,
                                'semester_id'     => $semesterAktif->id,
                                'is_active'       => true,
                                'created_at'      => now(),
                                'updated_at'      => now()
                            ]);

                            $siswa = Siswa::find($sId);
                            if ($siswa) {
                                $siswa->update(['is_active' => true]);
                            }
                            $summary['berhasil_naik']++;
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Proses kenaikan/kelulusan selesai',
                'detail'  => $summary
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kenaikan Kelas Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}