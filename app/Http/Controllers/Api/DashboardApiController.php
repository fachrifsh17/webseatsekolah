<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Berita, Pengumuman, Siswa, GuruStaf, Pesan, LogAdmin, Presensi, PoinSiswa, Orangtua};
use App\Http\Resources\{BeritaResource, PesanResource, LogAdminResource};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru,Siswa,Orangtua');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $roleName = $user->role ? $user->role->nama : 'Guest';

            $data = [

                'common' => [
                    'recent_pengumuman' => Pengumuman::latest()->take(3)->get(),
                    'recent_berita'     => BeritaResource::collection(Berita::latest()->take(3)->get()),
                ]
            ];

            if ($roleName === 'Admin') {
                $data['statistics'] = [
                    'total_berita' => Berita::count(),
                    'total_guru'   => GuruStaf::count(),
                    'total_siswa'  => Siswa::count(),
                    'pesan_baru'   => Pesan::where('is_read', 0)->count(),
                ];
                $data['recent_logs'] = LogAdminResource::collection(LogAdmin::with('user')->latest()->take(5)->get());
            } elseif ($roleName === 'Guru') {
                $data['statistics'] = [
                    'total_siswa_binaan' => Siswa::where('guru_id', $user->id)->count(),
                    'presensi_hari_ini'  => Presensi::where('guru_id', $user->id)->whereDate('created_at', today())->count(),
                ];
            } elseif ($roleName === 'Siswa') {
                $data['statistics'] = $this->getSiswaStats($user->id);
            } elseif ($roleName === 'Orangtua') {
                $data['statistics'] = $this->getOrangtuaStats($user->id);
            }

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    private function getSiswaStats($siswaId) {
        return [
            'positive_point' => (int) PoinSiswa::where('siswa_id', $siswaId)->where('skor', '>', 0)->sum('skor'),
            'negative_point' => (int) PoinSiswa::where('siswa_id', $siswaId)->where('skor', '<', 0)->sum('skor'),
        ];
    }
    
    private function getOrangtuaStats($userId) {
        $daftarAnak = Orangtua::with('siswa')->where('user_id', $userId)->get();
        return $daftarAnak->map(fn($item) => [
            'nama_siswa' => $item->siswa->nama ?? '-',
            'data' => $this->getSiswaStats($item->siswa_id)
        ]);
    }
}