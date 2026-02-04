<?php

namespace App\Http\Controllers\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Http\Requests\KenaikanKelasRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class KenaikanKelasController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('log.admin')->only(['prosesMassal']);
    }

    public function index(): JsonResponse
    {
        $data = Kelas::with(['jurusan', 'tahunAjaran'])
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $data
        ], Response::HTTP_OK);
    }

    public function prosesMassal(KenaikanKelasRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $summary = [
            'berhasil_naik' => 0,
            'lulus'         => 0,
            'peringatan'    => []
        ];

        try {
            DB::transaction(function () use ($validated, &$summary) {
                foreach ($validated['mapping'] as $map) {
                    $kelasLama = Kelas::find($map['kelas_lama_id']);
                    
                    if (!$kelasLama) {
                        $summary['peringatan'][] = "Kelas lama ID {$map['kelas_lama_id']} tidak ditemukan.";
                        continue;
                    }

                    $isKelulusan = empty($map['kelas_baru_id']);
                    $excludedIds = $map['excluded_siswa_ids'] ?? [];

                    $querySiswa = Siswa::where('kelas_id', $kelasLama->id)
                        ->where('is_active', true) 
                        ->whereNotIn('id', $excludedIds);

                    if ($isKelulusan) {
                        $count = $querySiswa->update([
                            'is_active' => false,
                        ]);
                        $summary['lulus'] += $count;
                    } else {
                        $kelasBaru = Kelas::find($map['kelas_baru_id']);

                        if (!$kelasBaru || $kelasLama->id === $kelasBaru->id) {
                            $summary['peringatan'][] = "Mapping tidak valid untuk kelas {$kelasLama->nama_kelas}.";
                            continue;
                        }

                        $count = $querySiswa->update([
                            'kelas_id' => $kelasBaru->id,
                        ]);
                        $summary['berhasil_naik'] += $count;
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Proses kenaikan/kelulusan kelas berhasil diselesaikan',
                'detail'  => $summary
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Kenaikan Kelas Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses kenaikan kelas',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}