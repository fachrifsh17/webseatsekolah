<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, TahunAjaran};
use App\Http\Requests\KenaikanKelasRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class KenaikanKelasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin');
        $this->middleware('log.admin')->only(['prosesMassal']);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 10);

            $data = Kelas::with(['jurusan', 'tahunAjaran'])
                ->whereHas('tahunAjaran', function($q) {
                    $q->where('is_active', true);
                })
                ->where('is_active', true)
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data'    => $data->items(),
                'meta'    => [
                    'current_page' => $data->currentPage(),
                    'last_page'    => $data->lastPage(),
                    'per_page'     => $data->perPage(),
                    'total'        => $data->total(),
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

    public function prosesMassal(KenaikanKelasRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif || strtolower($tahunAktif->semester) !== 'ganjil') {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: Proses kenaikan/kelulusan hanya bisa dilakukan setelah Tahun Ajaran Baru (Ganjil) diaktifkan.'
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

                    if ($kelasLama->tahun_ajaran_id === $tahunAktif->id) {
                        $summary['peringatan'][] = "Kelas {$kelasLama->nama_kelas} sudah berada di Tahun Ajaran aktif.";
                        continue;
                    }

                    $excludedIds = $map['excluded_siswa_ids'] ?? [];
                    if (empty($map['kelas_baru_id'])) {
                        $count = Siswa::where('kelas_id', $kelasLama->id)
                            ->where('is_active', true)
                            ->whereNotIn('id', $excludedIds)
                            ->update([
                                'is_active' => false
                            ]);
                        $summary['lulus'] += $count;
                    } 
                    else {
                        $kelasBaru = Kelas::find($map['kelas_baru_id']);

                        if (!$kelasBaru || $kelasLama->jurusan_id !== $kelasBaru->jurusan_id) {
                            $summary['peringatan'][] = "Jurusan tidak cocok untuk kelas {$kelasLama->nama_kelas}.";
                            continue;
                        }

                        if ($kelasBaru->tahun_ajaran_id !== $tahunAktif->id) {
                            $summary['peringatan'][] = "Kelas tujuan {$kelasBaru->nama_kelas} bukan bagian dari Tahun Ajaran aktif.";
                            continue;
                        }

                        $count = Siswa::where('kelas_id', $kelasLama->id)
                            ->where('is_active', true)
                            ->whereNotIn('id', $excludedIds)
                            ->update(['kelas_id' => $kelasBaru->id]);
                        
                        $summary['berhasil_naik'] += $count;
                    }
                    if (!empty($excludedIds)) {
                        $kelasTetap = Kelas::where('nama_kelas', $kelasLama->nama_kelas)
                            ->where('tahun_ajaran_id', $tahunAktif->id)
                            ->first();

                        if ($kelasTetap) {
                            $countTidakNaik = Siswa::whereIn('id', $excludedIds)
                                ->where('kelas_id', $kelasLama->id)
                                ->where('is_active', true)
                                ->update([
                                    'kelas_id' => $kelasTetap->id
                                ]);
                            $summary['tidak_naik'] += $countTidakNaik;
                        } else {
                            $summary['peringatan'][] = "Wadah kelas untuk siswa tinggal kelas di {$kelasLama->nama_kelas} belum tersedia di Tahun Ajaran baru.";
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Proses kenaikan/kelulusan massal berhasil diselesaikan',
                'detail'  => $summary
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kenaikan Kelas Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses perubahan data siswa',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}