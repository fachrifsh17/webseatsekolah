<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\{Siswa, Kelas, TahunAjaran};
use App\Http\Requests\KenaikanKelasRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class KenaikanKelasController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.aktivitas')->only(['prosesMassal']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Kelas::class);

        try {
            $perPage = min((int) $request->get('per_page', 10), 100);

            $data = Kelas::with(['jurusan', 'tahunAjaran'])
                ->whereHas('tahunAjaran', fn($q) => $q->where('is_active', true))
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
            Log::error('Index Kenaikan Kelas Error: ' . $e->getMessage());
            return $this->errorResponse('Gagal mengambil data kelas.');
        }
    }

    public function prosesMassal(KenaikanKelasRequest $request): JsonResponse
    {
        $this->authorize('update', Siswa::class);

        $validated = $request->validated();
        $tahunAktif = TahunAjaran::where('is_active', true)->first();

        if (!$tahunAktif || strtolower($tahunAktif->semester) !== 'ganjil') {
            return $this->errorResponse('Gagal: Proses kenaikan hanya bisa dilakukan saat Tahun Ajaran Ganjil aktif.', Response::HTTP_BAD_REQUEST);
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
                        $summary['peringatan'][] = "Kelas {$kelasLama->nama_kelas} sudah di periode aktif.";
                        continue;
                    }

                    $excludedIds = $map['excluded_siswa_ids'] ?? [];

                    if (empty($map['kelas_baru_id'])) {
                        $count = Siswa::where('kelas_id', $kelasLama->id)
                            ->where('is_active', true)
                            ->whereNotIn('id', $excludedIds)
                            ->update(['is_active' => false]);
                        $summary['lulus'] += $count;
                    } else {
                        $kelasBaru = Kelas::find($map['kelas_baru_id']);
                        if (!$kelasBaru || $kelasBaru->tahun_ajaran_id !== $tahunAktif->id) {
                            $summary['peringatan'][] = "Kelas tujuan {$kelasLama->nama_kelas} tidak valid.";
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
                                ->update(['kelas_id' => $kelasTetap->id]);
                            $summary['tidak_naik'] += $countTidakNaik;
                        } else {
                            $summary['peringatan'][] = "Wadah kelas tetap untuk {$kelasLama->nama_kelas} belum ada.";
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Proses kenaikan/kelulusan massal berhasil',
                'detail'  => $summary
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Proses Kenaikan Error: ' . $e->getMessage());
            return $this->errorResponse('Gagal memproses perubahan data siswa.');
        }
    }

    private function errorResponse(string $message, int $code = Response::HTTP_INTERNAL_SERVER_ERROR): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $code);
    }
}