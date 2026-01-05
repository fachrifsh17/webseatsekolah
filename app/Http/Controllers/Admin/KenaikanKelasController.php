<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Http\Requests\KenaikanKelasRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Throwable;

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
            'message' => 'Daftar kelas untuk pemetaan kenaikan',
            'data'    => $data
        ]);
    }

    public function prosesMassal(KenaikanKelasRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $totalSiswaTerpindah = 0;
        $konflikKelas = [];

        try {
            DB::beginTransaction();

            foreach ($validated['mapping'] as $map) {
                // 1. Cek apakah masih ada siswa aktif di kelas asal
                $siswaAktif = Siswa::where('kelas_id', $map['kelas_lama_id'])
                    ->where('status_aktif', '1')
                    ->count();

                // 2. Jika tidak ada siswa, tandai sebagai konflik/sudah diproses
                if ($siswaAktif === 0) {
                    $namaKelas = Kelas::find($map['kelas_lama_id'])->nama_kelas ?? 'ID ' . $map['kelas_lama_id'];
                    $konflikKelas[] = "Kelas $namaKelas tidak memiliki siswa aktif untuk dipindahkan (mungkin sudah diproses).";
                    continue;
                }

                // 3. Eksekusi pemindahan
                $count = Siswa::where('kelas_id', $map['kelas_lama_id'])
                    ->where('status_aktif', '1') 
                    ->update(['kelas_id' => $map['kelas_baru_id']]);
                
                $totalSiswaTerpindah += $count;
            }

            // 4. Jika ada kelas yang konflik dan tidak ada siswa sama sekali yang pindah
            if (count($konflikKelas) > 0 && $totalSiswaTerpindah === 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Konflik data terdeteksi',
                    'errors'  => ['mapping' => $konflikKelas]
                ], 409); // Status 409 Conflict
            }

            DB::commit();

            return response()->json([
                'message' => 'Proses kenaikan kelas selesai',
                'detail'  => [
                    'total_siswa_dipindahkan' => $totalSiswaTerpindah,
                    'jumlah_kelas_diproses'   => count($validated['mapping']),
                    'peringatan_konflik'      => $konflikKelas
                ]
            ], 200);

        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses kenaikan kelas',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }
}