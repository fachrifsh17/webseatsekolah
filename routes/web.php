<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\{
    AuthController,
    DashboardController,
    BeritaController,
    PengumumanController,
    PrestasiController,
    FasilitasController,
    JurusanController,
    MapelController,
    AlbumController,
    BannerController,
    GuruController,
    KurikulumController,
    SettingController,
    DataKontakController,
    EkstrakurikulerController,
    KalenderController,
    MediaController,
    PortalController,
    PpdbLinkController,
    ProfilSekolahController,
    RoleController,
    StrukturJabatanController,
    UserController,
    LogAdminController,
    PesanController,
    // Tambahkan Controller baru di sini
    PresensiGuruMapelController 
};

/*
|--------------------------------------------------------------------------
| API ROUTES (ADMIN PANEL)
|--------------------------------------------------------------------------
*/

// Public Login
Route::post('/login', [AuthController::class, 'login']);

// Grouping dengan Middleware Auth Token Kustom
Route::middleware(['auth.token'])->group(function () {

    // Global: Logout & Dashboard (Bisa diakses Admin & Guru)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // ========================================================
    // 1. AKSES FULL: ADMIN
    // ========================================================
    Route::middleware(['role:Admin'])->prefix('admin')->as('admin.')->group(function () {

        // CRUD Resources
        Route::apiResources([
            'berita'              => BeritaController::class,
            'pengumuman'          => PengumumanController::class,
            'prestasi'            => PrestasiController::class,
            'fasilitas'           => FasilitasController::class,
            'jurusan'             => JurusanController::class,
            'mapel'               => MapelController::class,
            'album'               => AlbumController::class,
            'banner'              => BannerController::class,
            'guru'                => GuruController::class,
            'ekstrakurikuler'     => EkstrakurikulerController::class,
            'kalender'            => KalenderController::class,
            'media'               => MediaController::class,
            'portal'              => PortalController::class,
            'role'                => RoleController::class,
            'user'                => UserController::class,
            'ppdb'                => PpdbLinkController::class,
            'kurikulum'           => KurikulumController::class,
            
            // --- Fitur Baru: Admin bisa Lihat, Edit, & Hapus semua presensi ---
            'presensi-guru-mapel' => PresensiGuruMapelController::class, 
        ]);

        // Resources dengan Parameter Khusus
        Route::apiResource('struktur', StrukturJabatanController::class)
            ->parameters(['struktur' => 'strukturJabatan']);

        // Monitoring & Pesan
        Route::apiResource('log', LogAdminController::class)->only(['index', 'show']);
        Route::apiResource('pesan', PesanController::class)->only(['index', 'show', 'destroy']);
        Route::patch('pesan/{pesan}/status', [PesanController::class, 'updateStatus']);

        // Data Tunggal
        Route::get('profil', [ProfilSekolahController::class, 'index']);
        Route::put('profil', [ProfilSekolahController::class, 'update']);
        Route::get('setting', [SettingController::class, 'index']);
        Route::post('setting/general', [SettingController::class, 'updateGeneral']);
        Route::post('setting/kontak', [SettingController::class, 'updateKontak']);
        Route::get('datakontak', [DataKontakController::class, 'index']);
        Route::put('datakontak', [DataKontakController::class, 'update']);
    });

    // ========================================================
    // 2. AKSES TERBATAS: GURU
    // ========================================================
    Route::middleware(['role:Guru'])->prefix('guru')->as('guru.')->group(function () {
        Route::apiResource('berita', BeritaController::class)->only(['index', 'store', 'show', 'update']);
        Route::get('profil-saya', [UserController::class, 'show']); 

        // --- Fitur Baru Khusus Operasional Guru ---
        // Guru Menginput Presensi (Header & Detail sekaligus)
        Route::post('input-presensi-mapel', [PresensiGuruMapelController::class, 'store']);
        
        // Guru melihat riwayat presensi yang pernah dia buat sendiri
        Route::get('riwayat-presensi', [PresensiGuruMapelController::class, 'index']);
        
        // Guru melihat detail satu presensi (untuk cek daftar siswa)
        Route::get('riwayat-presensi/{id}', [PresensiGuruMapelController::class, 'show']);
    });
});