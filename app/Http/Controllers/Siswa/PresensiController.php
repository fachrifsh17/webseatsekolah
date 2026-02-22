<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\{Presensi, TahunAjaran};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Log};
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Throwable;

class PresensiController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Siswa');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $siswa = $user->siswa;
            $siswaId = $user->siswa_id ?? $siswa?->id;

            if (!$siswaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data profil siswa tidak ditemukan.'
                ], Response::HTTP_NOT_FOUND);
            }

            $tahunAjaranId = $request->tahun_ajaran_id;
            
            if (!$tahunAjaranId) {
                $tahunAktif = TahunAjaran::where('is_active', 1)->first();
                $tahunAjaranId = $tahunAktif?->id;
            } else {
                $tahunAktif = TahunAjaran::find($tahunAjaranId);
            }

            $query = Presensi::where('siswa_id', $siswaId);

            if ($tahunAjaranId) {
                $query->where('tahun_ajaran_id', $tahunAjaranId);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('status', 'like', "%{$search}%")
                      ->orWhere('keterangan', 'like', "%{$search}%")
                      ->orWhere('tanggal', 'like', "%{$search}%");
                });
            }

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->tanggal);
            }

            $perPage = $request->integer('per_page', 10);
            $data = $query->orderBy('tanggal', 'desc')
                          ->orderBy('id', 'desc')
                          ->paginate($perPage);

            // Transformasi data untuk menyertakan meta paginasi mendalam
            $paginationData = $data->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Data presensi berhasil diambil.',
                'header' => [
                    'nama' => $siswa?->nama_lengkap,
                    'kelas' => $siswa?->kelas?->nama_kelas,
                    'tahun_ajaran' => $tahunAktif?->nama ?? 'Tidak Diketahui',
                    'semester' => $tahunAktif?->semester ?? '-',
                ],
                'data' => collect($data->items())->map(function($item) {
                    return [
                        'id' => $item->id,
                        'tanggal' => Carbon::parse($item->tanggal)->format('Y-m-d'),
                        'status' => $item->status,
                        'keterangan' => $item->keterangan,
                    ];
                }),
                'meta' => [
                    'current_page'  => $paginationData['current_page'],
                    'last_page'     => $paginationData['last_page'],
                    'per_page'      => $paginationData['per_page'],
                    'total'         => $paginationData['total'],
                    'from'          => $paginationData['from'],
                    'to'            => $paginationData['to'],
                    'path'          => $paginationData['path'],
                    'next_page_url' => $paginationData['next_page_url'],
                    'prev_page_url' => $paginationData['prev_page_url'],
                    'links'         => array_map(function ($link) {
                        return [
                            'url'    => $link['url'],
                            'label'  => $link['label'],
                            'page'   => is_numeric($link['label']) ? (int) $link['label'] : null,
                            'active' => $link['active'],
                        ];
                    }, $paginationData['links']),
                ],
            ], Response::HTTP_OK);

        } catch (Throwable $e) {
            Log::error('Gagal mengambil daftar presensi: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data presensi.',
                'errors'  => ['exception' => [$e->getMessage()]]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}