<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Models\Pengumuman;
use App\Models\GuruStaf;
use App\Models\Pesan;
use App\Models\LogAdmin;
use App\Http\Resources\BeritaResource;
use App\Http\Resources\PesanResource;
use App\Http\Resources\LogAdminResource;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.token');
        $this->middleware('role:Admin,Guru');
    }

    public function index(): JsonResponse
    {
        $counts = [
            'berita'      => Berita::count(),
            'pengumuman'  => Pengumuman::count(),
            'guru'        => GuruStaf::count(),
            'pesan_baru'  => Pesan::where('is_read', 0)->count(),
        ];

        $recent_berita = Berita::latest()->take(5)->get();
        $recent_pesan  = Pesan::latest()->take(5)->get();
        $recent_logs   = LogAdmin::with('user')->latest()->take(10)->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'counts'        => $counts,
                'recent_berita' => BeritaResource::collection($recent_berita),
                'recent_pesan'  => PesanResource::collection($recent_pesan),
                'recent_logs'   => LogAdminResource::collection($recent_logs),
            ],
        ]);
    }
}