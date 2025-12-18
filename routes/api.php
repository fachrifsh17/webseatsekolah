<?php

use Illuminate\Support\Facades\Route;

// Import Controllers
use App\Http\Controllers\Admin\{
    AuthController, DashboardController, BeritaController, PengumumanController,
    GuruController, JurusanController, KurikulumController, KalenderController, 
    MediaController, AlbumController, BannerController, FasilitasController, 
    EkstrakurikulerController, SettingController, StrukturJabatanController, 
    RoleController, UserController, PesanController, LogAdminController,
    PrestasiController, PortalController, PpdbLinkController, ProfilSekolahController
};

/*
|--------------------------------------------------------------------------
| API Routes - Admin & Guru Version
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. PUBLIC ROUTES (Tanpa Login)
// ==========================================
Route::prefix('public')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    // Data Read-Only untuk pengunjung website
    Route::get('berita', [BeritaController::class, 'index']);
    Route::get('berita/{berita}', [BeritaController::class, 'show']);
    Route::get('profil-sekolah', [ProfilSekolahController::class, 'index']);
    Route::get('guru', [GuruController::class, 'index']);
    
    // Form Kontak (Public mengirim ke Admin)
    Route::post('pesan', [PesanController::class, 'store']);
});

// ==========================================
// 2. PROTECTED ROUTES (Wajib Token)
// ==========================================
Route::middleware(['auth.token'])->group(function () {
    
    // Bisa diakses oleh siapapun yang login (Admin & Guru)
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('dashboard', [DashboardController::class, 'index']);

    // ==========================================
    // KHUSUS ROLE: ADMIN (Akses Penuh)
    // ==========================================
    Route::middleware(['role:Admin'])->prefix('admin')->group(function () {
        
        // Manajemen Akun & Hak Akses
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::get('logs', [LogAdminController::class, 'index']);

        // Konten Website (CRUD)
        Route::apiResource('berita', BeritaController::class);
        Route::apiResource('pengumuman', PengumumanController::class);
        Route::apiResource('prestasi', PrestasiController::class);
        Route::apiResource('fasilitas', FasilitasController::class);
        Route::apiResource('ekstrakurikuler', EkstrakurikulerController::class);
        
        // Akademik & Profil
        Route::apiResource('guru', GuruController::class);
        Route::apiResource('jurusan', JurusanController::class);
        Route::apiResource('kurikulum', KurikulumController::class);
        Route::apiResource('kalender', KalenderController::class);
        Route::apiResource('struktur-jabatan', StrukturJabatanController::class)
             ->parameters(['struktur-jabatan' => 'strukturJabatan']);

        // Gallery & Media
        Route::apiResource('album', AlbumController::class);
        Route::apiResource('media', MediaController::class);
        
        // Pengaturan & Pesan
        Route::apiResource('pesan', PesanController::class)->except(['store']);
        Route::post('setting/general', [SettingController::class, 'updateGeneral']);
        Route::post('setting/kontak', [SettingController::class, 'updateKontak']);
        Route::put('profil-sekolah', [ProfilSekolahController::class, 'update']);
    });

    // ==========================================
    // KHUSUS ROLE: GURU (Akses Terbatas)
    // ==========================================
    Route::middleware(['role:Guru'])->prefix('guru')->group(function () {
        // Contoh: Guru hanya boleh posting Berita atau Pengumuman saja
        Route::get('berita', [BeritaController::class, 'index']);
        Route::post('berita', [BeritaController::class, 'store']);
        
        // Guru boleh melihat daftar guru lain tapi tidak boleh hapus
        Route::get('daftar-guru', [GuruController::class, 'index']);
    });
});