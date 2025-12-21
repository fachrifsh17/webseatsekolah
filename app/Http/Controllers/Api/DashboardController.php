<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Orangtua;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function siswa(Request $request)
    {
        $siswa = Siswa::where('user_id', $request->user()->id)
            ->with(['kelas', 'jurusan'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'profil' => $siswa,
                'statistik' => [
                    'total_poin' => $siswa->poinSiswa()->sum('poin_positif') - $siswa->poinSiswa()->sum('poin_negatif'),
                    'kehadiran' => $siswa->presensi()->where('status', 'Hadir')->count(),
                    'izin_sakit' => $siswa->presensi()->whereIn('status', ['Izin', 'Sakit'])->count(),
                    'alfa' => $siswa->presensi()->where('status', 'Alfa')->count(),
                ]
            ]
        ]);
    }

    public function orangtua(Request $request)
    {
        $ortu = Orangtua::where('user_id', $request->user()->id)->firstOrFail();
        
        // Mengambil data semua anak yang terhubung dengan orang tua ini
        $anak = Siswa::where('orangtua_id', $ortu->id)
            ->with(['kelas', 'poinSiswa', 'presensi'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'nama_orangtua' => $ortu->nama_lengkap,
                'jumlah_anak' => $anak->count(),
                'daftar_anak' => $anak->map(function($item) {
                    return [
                        'nama' => $item->nama_lengkap,
                        'kelas' => $item->kelas->nama_kelas ?? '-',
                        'poin' => $item->poinSiswa->sum('poin_positif') - $item->poinSiswa->sum('poin_negatif'),
                        'kehadiran_terakhir' => $item->presensi()->latest()->first()->status ?? 'Belum ada data'
                    ];
                })
            ]
        ]);
    }
}