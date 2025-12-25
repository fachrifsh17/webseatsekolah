<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Sistem Informasi Sekolah Multi-Role
|--------------------------------------------------------------------------
*/

// Import Controller Admin (Backend/Manajemen)
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
    PresensiGuruMapelController
};

/*
|--------------------------------------------------------------------------
| 1) PUBLIC ROUTES -> /api/public/...
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::post('login', [AuthController::class, 'login']); // /api/public/login
    // Tambahkan endpoint publik lain di sini (login-api, register, dsb) jika perlu
});

/*
|--------------------------------------------------------------------------
| 2) PROTECTED ROUTES -> semua admin/manajemen di bawah /api/admin/...
|    - auth.token dipakai untuk semua route yang butuh autentikasi
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.token'])->group(function () {

    /*
    |-----------------------------------------------------------------------
    | Basic admin endpoints di /api/admin/...
    |-----------------------------------------------------------------------
    */
    Route::prefix('admin')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);        // /api/admin/logout
        Route::get('dashboard', [DashboardController::class, 'index']);  // /api/admin/dashboard
        Route::get('me', [AuthController::class, 'me']);                // /api/admin/me
    });

    /*
    |-----------------------------------------------------------------------
    | Akses penuh Admin
    | Semua resource manajemen di /api/admin/...
    |-----------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware(['role:Admin'])->as('admin.')->group(function () {

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
            'ppdb-link'           => PpdbLinkController::class,
            'kurikulum'           => KurikulumController::class,
            'presensi-guru-mapel' => PresensiGuruMapelController::class,
        ]);

        // Resource dengan parameter khusus
        Route::apiResource('struktur', StrukturJabatanController::class)
            ->parameters(['struktur' => 'strukturJabatan']);

        // Monitoring & Pesan
        Route::apiResource('log', LogAdminController::class)->only(['index', 'show']);
        Route::apiResource('pesan', PesanController::class)->only(['index', 'show', 'destroy']);
        Route::patch('pesan/{pesan}/status', [PesanController::class, 'updateStatus']);

        // Data tunggal: profil & setting
        Route::get('profil', [ProfilSekolahController::class, 'index']);
        Route::put('profil', [ProfilSekolahController::class, 'update']);

        Route::get('setting', [SettingController::class, 'index']);
        Route::put('setting/general', [SettingController::class, 'updateGeneral']);
        Route::put('setting/kontak', [SettingController::class, 'updateKontak']);

        Route::get('datakontak', [DataKontakController::class, 'index']);
        Route::put('datakontak', [DataKontakController::class, 'update']);
    });

    /*
    |-----------------------------------------------------------------------
    | Akses terbatas Guru
    | Semua route guru berada di /api/admin/guru/...
    |-----------------------------------------------------------------------
    */
    Route::prefix('admin')->group(function () {
        Route::prefix('guru')->middleware(['role:Guru'])->as('guru.')->group(function () {
            // Guru boleh mengelola berita terbatas
            Route::apiResource('berita', BeritaController::class)->only(['index', 'store', 'show', 'update']);

            // Profil guru
            Route::get('profil-saya', [UserController::class, 'show']);

            // Presensi mapel
            Route::post('input-presensi-mapel', [PresensiGuruMapelController::class, 'store']);
            Route::get('riwayat-presensi', [PresensiGuruMapelController::class, 'index']);
            Route::get('riwayat-presensi/{id}', [PresensiGuruMapelController::class, 'show']);
        });
    });
});
