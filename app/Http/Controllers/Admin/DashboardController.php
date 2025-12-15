<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

class DashboardController extends Controller {
  public function index(){
    // ringkasan statistik sederhana
    $counts = [
      'berita' => \App\Models\Berita::count(),
      'pengumuman' => \App\Models\Pengumuman::count(),
      'guru' => \App\Models\GuruStaf::count(),
    ];
    return view('admin.dashboard.index', compact('counts'));
  }
}
