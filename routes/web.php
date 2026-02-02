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
    PresensiGuruMapelController,
    KenaikanKelasController
};

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES -> /api/public/...
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES -> /api/admin/... (auth.token + role:Admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth.token'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('me', [AuthController::class, 'me']);

    Route::middleware(['role:Admin'])->as('admin.')->group(function () {
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

        // Kenaikan Kelas Massal
        Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index']);
        Route::post('kenaikan-kelas/proses', [KenaikanKelasController::class, 'prosesMassal']);

        Route::apiResource('struktur', StrukturJabatanController::class)
            ->parameters(['struktur' => 'strukturJabatan']);

        Route::apiResource('log', LogAdminController::class)->only(['index', 'show']);
        Route::apiResource('pesan', PesanController::class)->only(['index', 'show', 'destroy']);
        Route::patch('pesan/{pesan}/status', [PesanController::class, 'updateStatus']);

        // Profil Sekolah
        Route::get('profil', [ProfilSekolahController::class, 'index']);
        Route::put('profil', [ProfilSekolahController::class, 'update']);

        // Setting
        Route::get('setting', [SettingController::class, 'index']);
        Route::put('setting/general', [SettingController::class, 'updateGeneral']);
        Route::put('setting/kontak', [SettingController::class, 'updateKontak']);

        // Data Kontak
        Route::get('datakontak', [DataKontakController::class, 'index']);
        Route::put('datakontak', [DataKontakController::class, 'update']);
    });

    /*
    |--------------------------------------------------------------------------
    | GURU ROUTES -> /api/admin/guru/... (auth.token + role:Guru)
    |--------------------------------------------------------------------------
    */
    Route::prefix('guru')->middleware(['role:Guru'])->as('guru.')->group(function () {
        Route::apiResource('berita', BeritaController::class)->only(['index', 'store', 'show', 'update']);
        Route::get('profil-saya', [UserController::class, 'show']);
        Route::post('input-presensi-mapel', [PresensiGuruMapelController::class, 'store']);
        Route::get('riwayat-presensi', [PresensiGuruMapelController::class, 'index']);
        Route::get('riwayat-presensi/{id}', [PresensiGuruMapelController::class, 'show']);
    });
});
