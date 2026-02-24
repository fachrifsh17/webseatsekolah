<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, TahunAjaran};
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
        $this->middleware('log.aktivitas')->only(['prosesMassal']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Kelas::class);

        try {
            $perPage = $request->get('per_page', 10);

            $data = Kelas::with(['jurusan'])
                ->where('is_active', true)
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => $data->items(),
                'meta'    => [
                    'current_page'  => $data->currentPage(),
                    'last_page'     => $data->lastPage(),
                    'per_page'      => $data->perPage(),
                    'total'         => $data->total(),
                ],
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kelas.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function prosesMassal(Request $request): JsonResponse
    {
        $this->authorize('create', Kelas::class);

        // Validasi Manual di dalam Controller agar tidak error "wajib diisi"
        $validator = Validator::make($request->all(), [
            'mapping' => 'required|array',
            'mapping.*.kelas_lama_id' => 'required|exists:kelas,id',
            'mapping.*.kelas_baru_id' => 'nullable', // Dibuat nullable agar bisa Lulus (null)
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
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif || strtolower($tahunAktif->semester) !== 'ganjil') {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Proses hanya bisa dilakukan saat Tahun Ajaran Ganjil aktif.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $summary = [
            'berhasil_naik' => 0,
            'lulus'         => 0,
            'tidak_naik'    => 0,
            'peringatan'    => []
        ];

        try {
            DB::transaction(function () use ($validated, $tahunAktif, &$summary) {
                foreach ($validated['mapping'] as $map) {
                    $kelasLama = Kelas::find($map['kelas_lama_id']);
                    if (!$kelasLama) continue;

                    // Validasi pengaman: Lulus hanya untuk kelas XII / 12
                    if (empty($map['kelas_baru_id'])) {
                        $isKelasAkhir = str_contains(strtoupper($kelasLama->nama_kelas), 'XII') || 
                                        str_contains($kelasLama->nama_kelas, '12');
                        
                        if (!$isKelasAkhir) {
                            $summary['peringatan'][] = "Gagal Lulus: Kelas {$kelasLama->nama_kelas} bukan tingkat akhir.";
                            continue;
                        }
                    }

                    $excludedIds = $map['excluded_siswa_ids'] ?? [];

                    $siswaIds = DB::table('siswa_kelas')
                        ->where('kelas_id', $kelasLama->id)
                        ->where('is_active', true)
                        ->where('tahun_ajaran_id', '!=', $tahunAktif->id)
                        ->pluck('siswa_id');

                    $targetSiswaIds = $siswaIds->diff($excludedIds);

                    if (empty($map['kelas_baru_id'])) {
                        // PROSES LULUS
                        DB::table('siswa_kelas')->whereIn('siswa_id', $targetSiswaIds)->update(['is_active' => false]);
                        Siswa::whereIn('id', $targetSiswaIds)->update(['is_active' => false]);
                        $summary['lulus'] += $targetSiswaIds->count();
                    } 
                    else {
                        // PROSES NAIK KELAS
                        $kelasBaru = Kelas::find($map['kelas_baru_id']);

                        if (!$kelasBaru || $kelasLama->jurusan_id !== $kelasBaru->jurusan_id) {
                            $summary['peringatan'][] = "Jurusan tidak cocok: {$kelasLama->nama_kelas}.";
                            continue;
                        }

                        DB::table('siswa_kelas')->whereIn('siswa_id', $targetSiswaIds)->update(['is_active' => false]);
                        
                        foreach ($targetSiswaIds as $sId) {
                            DB::table('siswa_kelas')->insert([
                                'siswa_id'        => $sId,
                                'kelas_id'        => $kelasBaru->id,
                                'tahun_ajaran_id' => $tahunAktif->id,
                                'is_active'       => true,
                                'created_at'      => now(),
                                'updated_at'      => now()
                            ]);
                        }
                        $summary['berhasil_naik'] += $targetSiswaIds->count();
                    }

                    // PROSES TIDAK NAIK
                    if (!empty($excludedIds)) {
                        DB::table('siswa_kelas')->whereIn('siswa_id', $excludedIds)->update(['is_active' => false]);
                        
                        foreach ($excludedIds as $eId) {
                            DB::table('siswa_kelas')->insert([
                                'siswa_id'        => $eId,
                                'kelas_id'        => $kelasLama->id,
                                'tahun_ajaran_id' => $tahunAktif->id,
                                'is_active'       => true,
                                'created_at'      => now(),
                                'updated_at'      => now()
                            ]);
                        }
                        $summary['tidak_naik'] += count($excludedIds);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Proses selesai',
                'detail'  => $summary
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kenaikan Kelas Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}