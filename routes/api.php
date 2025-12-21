<?php

use Illuminate\Support\Facades\Route;

// Import Controllers dari folder Api (Untuk Public)
use App\Http\Controllers\Api\{
    BeritaApiController, PengumumanApiController, GuruApiController, 
    JurusanApiController, KurikulumApiController, KalenderApiController, 
    MediaApiController, AlbumApiController, BannerApiController, 
    FasilitasApiController, EkstrakurikulerApiController, 
    PesanApiController, DataKontakApiController, MapelApiController
};

// Import Controllers dari folder Admin (Untuk Admin & Guru)
use App\Http\Controllers\Admin\{
    AuthController, DashboardController, BeritaController, PengumumanController,
    GuruController, JurusanController, KurikulumController, KalenderController, 
    MediaController, AlbumController, BannerController, FasilitasController, 
    EkstrakurikulerController, SettingController, StrukturJabatanController, 
    RoleController, UserController, PesanController, LogAdminController,
    PrestasiController, ProfilSekolahController, MapelController
};

/*
|--------------------------------------------------------------------------
| 1. PUBLIC ROUTES (Akses Tanpa Login / Pengunjung Website)
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    // Auth Login (Admin/Guru tetap login lewat sini)
    Route::post('login', [AuthController::class, 'login']);

    // Data Read-Only (Menggunakan ApiController agar aman dari DELETE/POST liar)
    Route::get('berita', [BeritaApiController::class, 'index']);
    Route::get('berita/{berita}', [BeritaApiController::class, 'show']);
    Route::get('pengumuman', [PengumumanApiController::class, 'index']);
    Route::get('pengumuman/{pengumuman}', [PengumumanApiController::class, 'show']);
    Route::get('guru', [GuruApiController::class, 'index']);
    Route::get('jurusan', [JurusanApiController::class, 'index']);
    Route::get('kurikulum', [KurikulumApiController::class, 'index']);
    Route::get('kalender', [KalenderApiController::class, 'index']);
    Route::get('fasilitas', [FasilitasApiController::class, 'index']);
    Route::get('ekstrakurikuler', [EkstrakurikulerApiController::class, 'index']);
    Route::get('banner', [BannerApiController::class, 'index']);
    Route::get('media', [MediaApiController::class, 'index']);
    Route::get('album', [AlbumApiController::class, 'index']);
    Route::get('mapel', [MapelApiController::class, 'index']);
    Route::get('kontak', [DataKontakApiController::class, 'show']);
    Route::get('profil-sekolah', [ProfilSekolahController::class, 'index']);

    // Form Kontak (Public mengirim ke Admin)
    // Ditambahkan throttle:3,1 (Maksimal 3 pesan per menit per IP) untuk cegah SPAM
    Route::post('pesan', [PesanApiController::class, 'store'])->middleware('throttle:3,1');
});

/*
|--------------------------------------------------------------------------
| 2. PROTECTED ROUTES (Wajib Token)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.token'])->group(function () {
    
    // Global Auth Actions
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('dashboard', [DashboardController::class, 'index']);

    // ==========================================
    // KHUSUS ROLE: ADMIN (Akses Penuh CRUD)
    // ==========================================
    Route::middleware(['role:Admin'])->prefix('admin')->group(function () {
        
        // Manajemen Akun & Hak Akses
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::get('logs', [LogAdminController::class, 'index']);

        // Konten Website (CRUD Lengkap)
        Route::apiResource('berita', BeritaController::class);
        Route::apiResource('pengumuman', PengumumanController::class);
        Route::apiResource('prestasi', PrestasiController::class);
        Route::apiResource('fasilitas', FasilitasController::class);
        Route::apiResource('ekstrakurikuler', EkstrakurikulerController::class);
        Route::apiResource('banner', BannerController::class);
        
        // Akademik & Profil
        Route::apiResource('guru', GuruController::class);
        Route::apiResource('jurusan', JurusanController::class);
        Route::apiResource('kurikulum', KurikulumController::class);
        Route::apiResource('kalender', KalenderController::class);
        Route::apiResource('mapel', MapelController::class); // Jika ada Admin MapelController
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
        Route::get('berita', [BeritaController::class, 'index']);
        Route::post('berita', [BeritaController::class, 'store']);
        Route::get('daftar-guru', [GuruController::class, 'index']);
    });
});