<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Http\Requests\KenaikanKelasRequest;
use Illuminate\Support\Facades\DB;
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

    public function index(): JsonResponse
    {
        $data = Kelas::with(['jurusan', 'tahunAjaran'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar kelas untuk pemetaan kenaikan',
            'data'    => $data
        ], Response::HTTP_OK);
    }

    public function prosesMassal(KenaikanKelasRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $totalSiswaTerpindah = 0;
        $konflikKelas = [];

        try {
            DB::transaction(function () use ($validated, &$totalSiswaTerpindah, &$konflikKelas) {
                foreach ($validated['mapping'] as $map) {
                    $kelasLama = Kelas::find($map['kelas_lama_id']);
                    $kelasBaru = Kelas::find($map['kelas_baru_id']);

                    if (!$kelasLama || !$kelasBaru || $kelasLama->id === $kelasBaru->id) {
                        $konflikKelas[] = "Mapping kelas tidak valid untuk ID {$map['kelas_lama_id']} → {$map['kelas_baru_id']}.";
                        continue;
                    }

                    
                    $siswaCount = Siswa::where('kelas_id', $kelasLama->id)->count();

                    if ($siswaCount === 0) {
                        $konflikKelas[] = "Kelas {$kelasLama->nama_kelas} tidak memiliki siswa untuk dipindahkan.";
                        continue;
                    }

                
                    $count = Siswa::where('kelas_id', $kelasLama->id)
                        ->update(['kelas_id' => $kelasBaru->id]);

                    $totalSiswaTerpindah += $count;

                
                    $kelasLama->is_active = false;
                    $kelasLama->save();
                }

                if (count($konflikKelas) > 0 && $totalSiswaTerpindah === 0) {
                    throw new \Exception('Konflik data terdeteksi, tidak ada siswa yang dipindahkan.');
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Proses kenaikan kelas selesai',
                'detail'  => [
                    'total_siswa_dipindahkan' => $totalSiswaTerpindah,
                    'jumlah_kelas_diproses'   => count($validated['mapping']),
                    'peringatan_konflik'      => $konflikKelas
                ]
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses kenaikan kelas',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
