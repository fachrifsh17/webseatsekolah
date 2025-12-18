<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Models\Pengumuman;
use App\Models\GuruStaf;
use App\Models\Pesan;
use App\Models\LogAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth.token'),
            new Middleware('role:Admin,SuperAdmin'),
        ];
    }

    public function index(): JsonResponse
    {
        $counts = [
            'berita' => Berita::count(),
            'pengumuman' => Pengumuman::count(),
            'guru' => GuruStaf::count(),
            'pesan_baru' => Pesan::where('is_read', 0)->count(),
        ];

        $recent_berita = Berita::latest()->take(5)->get();
        $recent_pesan = Pesan::latest()->take(5)->get();
        
        $recent_logs = LogAdmin::with('user') 
                        ->latest()
                        ->take(10)
                        ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'counts' => $counts,
                'recent_berita' => $recent_berita,
                'recent_pesan' => $recent_pesan,
                'recent_logs' => $recent_logs
            ]
        ]);
    }
}