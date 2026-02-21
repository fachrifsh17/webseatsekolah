<?php

namespace App\Http\Controllers\Orangtua;

use App\Http\Controllers\Controller;
use App\Models\{JamSekolah, TahunAjaran, ProfilSekolah, DataKontak};
use App\Http\Resources\JamSekolahResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Barryvdh\DomPDF\Facade\Pdf;

class JamSekolahController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Orangtua');
        $this->authorizeResource(JamSekolah::class, 'jam_sekolah');
    }

    public function index(): JsonResponse
    {
        try {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();

            if (!$tahunAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada Tahun Ajaran yang aktif saat ini.',
                ], Response::HTTP_NOT_FOUND);
            }

            $data = JamSekolah::with('tahunAjaran')
                ->where('tahun_ajaran_id', $tahunAktif->id)
                ->orderBy('hari')
                ->orderBy('waktu_mulai')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => JamSekolahResource::collection($data),
                'meta'    => [
                    'tahun_ajaran' => $tahunAktif->nama,
                    'status'       => 'Aktif'
                ]
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jam sekolah.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export()
    {
        try {
            $this->authorize('viewAny', JamSekolah::class);

            $tahunAktif = TahunAjaran::where('is_active', true)->first();

            if (!$tahunAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal ekspor: Tahun ajaran aktif tidak ditemukan.'
                ], Response::HTTP_BAD_REQUEST);
            }

            $profil = ProfilSekolah::first();
            $kontak = DataKontak::first();
            $namaTA = str_replace(['/', '\\', ' '], '-', $tahunAktif->nama);
            $fileName = 'jam_sekolah_' . $namaTA . '-aktif.pdf';

            $dataPerHari = JamSekolah::where('tahun_ajaran_id', $tahunAktif->id)
                ->orderByRaw("FIELD(hari, 'Senin','Selasa','Rabu','Kamis','Jumat')")
                ->orderBy('waktu_mulai')
                ->get()
                ->groupBy('hari');

            $pdf = Pdf::loadView('exports.jam_sekolah_pdf', [
                'profil' => $profil,
                'kontak' => $kontak,
                'ta' => $tahunAktif,
                'dataPerHari' => $dataPerHari,
                'hariList' => ['Senin','Selasa','Rabu','Kamis','Jumat']
            ])->setPaper('a4', 'landscape');

            return $pdf->download($fileName);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor jadwal ke PDF.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $tahunAktif = TahunAjaran::where('is_active', true)->first();

            if (!$tahunAktif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak: Tidak ada tahun ajaran aktif.'
                ], Response::HTTP_FORBIDDEN);
            }

            $jamSekolah = JamSekolah::with('tahunAjaran')
                ->where('tahun_ajaran_id', $tahunAktif->id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => new JamSekolahResource($jamSekolah)
            ], Response::HTTP_OK);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data jam sekolah tidak ditemukan atau berada di luar tahun ajaran aktif.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
