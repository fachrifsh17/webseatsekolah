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
        $this->middleware('log.aktivitas')->only(['prosesMassal', 'generateFromPreviousYear']);
    }

    public function index(Request $request): JsonResponse
    {
        $semesterAktif = Semester::where('is_active', true)->first();

        $validator = Validator::make($request->all(), [
            'kelas_id'    => 'required|exists:kelas,id',
            'semester_id' => 'nullable|exists:semesters,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors'  => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $kelasId = $request->input('kelas_id');
        $semesterId = $request->input('semester_id') ?? ($semesterAktif ? $semesterAktif->id : null);

        if (!$semesterId) {
            return response()->json([
                'success' => false,
                'message' => 'Semester tidak ditemukan.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $querySiswa = DB::table('siswa_kelas')
            ->join('siswa', 'siswa_kelas.siswa_id', '=', 'siswa.id')
            ->where('siswa_kelas.kelas_id', $kelasId)
            ->where('siswa_kelas.semester_id', $semesterId)
            ->where('siswa.is_active', true);

        $riwayat = (clone $querySiswa)
            ->select(
                'siswa.id',
                'siswa.nama_lengkap',
                'siswa.nis',
                'siswa_kelas.is_active as status_di_kelas'
            )
            ->get();

        $semesterTerpilih = Semester::find($semesterId);

        return response()->json([
            'success' => true,
            'data'    => $riwayat,
            'count'   => $querySiswa->count(),
            'info'    => [
                'semester_nama' => $semesterTerpilih ? $semesterTerpilih->nama : null,
                'is_current_active' => $semesterAktif && $semesterAktif->id == $semesterId
            ]
        ], Response::HTTP_OK);
    }

    public function generateFromPreviousYear(): JsonResponse
    {
        $this->authorize('create', Kelas::class);
        set_time_limit(300);

        try {
            $semesterAktif = Semester::with('tahunAjaran')->where('is_active', true)->first();
            
            if (!$semesterAktif || strtolower($semesterAktif->nama) !== 'genap') {
                return response()->json([
                    'success' => false, 
                    'message' => 'Gagal: Fitur ini hanya tersedia saat Semester aktif berada di Semester Genap.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $tahunAjaranAktif = $semesterAktif->tahunAjaran;
            $semesterSumber = Semester::where('tahun_ajaran_id', $tahunAjaranAktif->id)
                ->where('nama', 'Ganjil')
                ->first();

            if (!$semesterSumber) {
                return response()->json(['success' => false, 'message' => 'Gagal: Data Semester Ganjil tidak ditemukan.'], Response::HTTP_NOT_FOUND);
            }

            $siswaGanjil = DB::table('siswa_kelas')
                ->join('siswa', 'siswa_kelas.siswa_id', '=', 'siswa.id')
                ->where('siswa_kelas.semester_id', $semesterSumber->id)
                ->where('siswa_kelas.is_active', true)
                ->where('siswa.is_active', true)
                ->select('siswa_kelas.*')
                ->get();

            if ($siswaGanjil->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Gagal: Tidak ada siswa aktif di Semester Ganjil.'], Response::HTTP_NOT_FOUND);
            }

            $countSiswa = 0;

            DB::transaction(function () use ($siswaGanjil, $semesterAktif, $semesterSumber, &$countSiswa) {
                foreach ($siswaGanjil as $item) {
                    $exists = DB::table('siswa_kelas')
                        ->where('siswa_id', $item->siswa_id)
                        ->where('semester_id', $semesterAktif->id)
                        ->exists();

                    if (!$exists) {
                        DB::table('siswa_kelas')
                            ->where('siswa_id', $item->siswa_id)
                            ->where('semester_id', $semesterSumber->id)
                            ->update(['is_active' => false]);

                        DB::table('siswa_kelas')->insert([
                            'siswa_id'        => $item->siswa_id,
                            'kelas_id'        => $item->kelas_id,
                            'semester_id'     => $semesterAktif->id,
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
        $semesterAktif = Semester::with('tahunAjaran')->where('is_active', true)->first();
        
        if (!$semesterAktif || !str_contains(strtolower($semesterAktif->nama), 'ganjil')) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Proses kenaikan kelas hanya bisa dilakukan di semester GANJIL (awal tahun ajaran).'
            ], Response::HTTP_BAD_REQUEST);
        }

        $semesterLama = Semester::where('id', '<', $semesterAktif->id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$semesterLama) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Tidak ditemukan data semester sebelumnya.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $summary = ['berhasil_naik' => 0, 'lulus' => 0, 'peringatan' => []];

        try {
            DB::transaction(function () use ($validated, $semesterAktif, $semesterLama, &$summary) {
                foreach ($validated['mapping'] as $map) {
                    $kelasLama = Kelas::with('tingkatan')->find($map['kelas_lama_id']);
                    if (!$kelasLama) continue;

                    $excludedIds = $map['excluded_siswa_ids'] ?? [];
                    $siswaIds = DB::table('siswa_kelas')
                        ->join('siswa', 'siswa_kelas.siswa_id', '=', 'siswa.id')
                        ->where('siswa_kelas.kelas_id', $kelasLama->id)
                        ->where('siswa_kelas.semester_id', $semesterLama->id)
                        ->where('siswa_kelas.is_active', true)
                        ->where('siswa.is_active', true)
                        ->pluck('siswa_kelas.siswa_id');

                    $targetSiswaIds = $siswaIds->diff($excludedIds);

                    if (empty($map['kelas_baru_id'])) {
                        $isKelasAkhir = str_contains(strtoupper($kelasLama->tingkatan->nama_tingkatan), 'XII') || 
                                        str_contains($kelasLama->tingkatan->nama_tingkatan, '12');
                        
                        if (!$isKelasAkhir) {
                            $summary['peringatan'][] = "Gagal Lulus: Kelas {$kelasLama->nama_kelas} bukan tingkat akhir.";
                            continue;
                        }

                        foreach ($targetSiswaIds as $sId) {
                            DB::table('siswa_kelas')
                                ->where('siswa_id', $sId)
                                ->where('kelas_id', $kelasLama->id)
                                ->where('semester_id', $semesterLama->id)
                                ->update(['is_active' => false]);

                            $siswa = Siswa::find($sId);
                            if ($siswa) {
                                $siswa->update(['is_active' => false]);
                                if ($siswa->user_id) {
                                    User::where('id', $siswa->user_id)->update(['is_active' => false]);
                                }
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
                        $kelasBaru = Kelas::with('tingkatan')->find($map['kelas_baru_id']);
                        
                        if ($kelasBaru->tingkatan_id > ($kelasLama->tingkatan_id + 1)) {
                            $summary['peringatan'][] = "Gagal Naik: Tidak boleh lompat tingkat.";
                            continue;
                        }

                        foreach ($targetSiswaIds as $sId) {
                            DB::table('siswa_kelas')
                                ->where('siswa_id', $sId)
                                ->where('kelas_id', $kelasLama->id)
                                ->where('semester_id', $semesterLama->id)
                                ->update(['is_active' => false]); 

                            DB::table('siswa_kelas')->insert([
                                'siswa_id'        => $sId,
                                'kelas_id'        => $kelasBaru->id,
                                'semester_id'     => $semesterAktif->id,
                                'is_active'       => true,
                                'created_at'      => now(),
                                'updated_at'      => now()
                            ]);

                            $siswa = Siswa::find($sId);
                            if ($siswa) { $siswa->update(['is_active' => true]); }
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
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}