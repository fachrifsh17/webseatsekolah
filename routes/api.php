<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Sistem Informasi Sekolah Multi-Role
|--------------------------------------------------------------------------
*/

// Import Controller API (Frontend/Public)
use App\Http\Controllers\Api\{
    BeritaApiController, PengumumanApiController, GuruApiController,
    JurusanApiController, KurikulumApiController, KalenderApiController,
    MediaApiController, AlbumApiController, BannerApiController,
    FasilitasApiController, EkstrakurikulerApiController,
    PesanApiController, DataKontakApiController, MapelApiController,
    SiswaApiController, OrangtuaApiController, PresensiApiController,
    JadwalProduktifApiController, PrestasiApiController, PoinSiswaApiController,
    PortalApiController, PPDBLinkApiController, KelasApiController,
    TahunAjaranApiController, JamSekolahApiController, AuthApiController as ApiAuth,
    DashboardApiController, SettingApiController,
    StrukturJabatanApiController, ProfilApiController
};

// Import Controller Admin (Backend/Manajemen)
use App\Http\Controllers\Admin\{
    AuthController as AdminAuth, BeritaController, PengumumanController,
    GuruController, JurusanController, KurikulumController, KalenderController,
    MediaController, AlbumController, BannerController, FasilitasController,
    EkstrakurikulerController, StrukturJabatanController,
    RoleController, UserController, PesanController, LogAdminController,
    PrestasiController, ProfilSekolahController, MapelController,
    SiswaController, OrangtuaController, PresensiController,
    JadwalProduktifController, PoinSiswaController, PortalController,
    PpdbLinkController, KelasController, TahunAjaranController,
    JamSekolahController, GuruMapelController, DataKontakController,
    PresensiGuruMapelController, SettingController
};

/*
|--------------------------------------------------------------------------
| 1. PUBLIC ROUTES (Tanpa Login) -> /api/public/...
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::post('login', [AdminAuth::class, 'login']);      // /api/public/login (admin/staf)
    Route::post('login-api', [ApiAuth::class, 'login']);   // /api/public/login-api (siswa/ortu)
    Route::post('register', [ApiAuth::class, 'register']); // /api/public/register

    // Konten publik
    Route::get('berita', [BeritaApiController::class, 'index']);
    Route::get('pengumuman', [PengumumanApiController::class, 'index']);
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
    Route::get('prestasi', [PrestasiApiController::class, 'index']);
    Route::get('portal', [PortalApiController::class, 'index']);
    Route::get('ppdb-link', [PPDBLinkApiController::class, 'index']);
    Route::get('kontak', [DataKontakApiController::class, 'show']);
    Route::get('profil-sekolah', [ProfilApiController::class, 'index']);
    Route::get('struktur', [StrukturJabatanApiController::class, 'index']);
    Route::post('pesan', [PesanApiController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| 2. PROTECTED ROUTES -> semua admin/manajemen di bawah /api/admin/...
|    - auth.token dipakai untuk semua route yang butuh autentikasi
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth.token'])->group(function () {

    // Basic admin endpoints (di /api/admin/...)
    Route::post('logout', [AdminAuth::class, 'logout']);
    Route::get('dashboard', [DashboardApiController::class, 'index']);
    Route::get('me', [AdminAuth::class, 'me']);

    /*
    |-----------------------------------------------------------------------
    | Akses penuh: Admin
    | Semua resource manajemen di /api/admin/...
    |-----------------------------------------------------------------------
    */
    Route::middleware(['role:Admin'])->group(function () {

        // Rute spesifik (letakkan sebelum apiResource agar tidak bentrok)
        Route::get('logs', [LogAdminController::class, 'index']);
        Route::match(['put', 'post'], 'profil-sekolah', [ProfilSekolahController::class, 'update']);
        Route::post('setting/update', [SettingController::class, 'updateGeneral']);
        Route::get('api-kelas-list', [KelasApiController::class, 'index']);
        Route::get('api-setting-list', [SettingApiController::class, 'index']);

        // CRUD Utama
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);

        // Manajemen Akademik
        Route::apiResource('siswa', SiswaController::class);
        Route::apiResource('orangtua', OrangtuaController::class);
        Route::apiResource('guru', GuruController::class);
        Route::apiResource('guru-mapel', GuruMapelController::class);
        Route::apiResource('jurusan', JurusanController::class);
        Route::apiResource('kurikulum', KurikulumController::class);
        Route::apiResource('kalender', KalenderController::class);
        Route::apiResource('kelas', KelasController::class);
        Route::apiResource('mapel', MapelController::class);
        Route::apiResource('tahun-ajaran', TahunAjaranController::class);
        Route::apiResource('jam-sekolah', JamSekolahController::class);
        Route::apiResource('jadwal-produktif', JadwalProduktifController::class);
        Route::apiResource('presensi', PresensiController::class);
        Route::apiResource('presensi-guru-mapel', PresensiGuruMapelController::class);
        Route::apiResource('poin-siswa', PoinSiswaController::class);

        // Manajemen Konten Website
        Route::apiResource('portal', PortalController::class);
        Route::apiResource('berita', BeritaController::class);
        Route::apiResource('pengumuman', PengumumanController::class);
        Route::apiResource('prestasi', PrestasiController::class);
        Route::apiResource('fasilitas', FasilitasController::class);
        Route::apiResource('ekstrakurikuler', EkstrakurikulerController::class);
        Route::apiResource('banner', BannerController::class);
        Route::apiResource('album', AlbumController::class);
        Route::apiResource('media', MediaController::class);
        Route::apiResource('ppdb-link', PpdbLinkController::class);
        Route::apiResource('struktur-jabatan', StrukturJabatanController::class);
        Route::apiResource('data-kontak', DataKontakController::class);
        Route::apiResource('pesan', PesanController::class)->except(['store']);
    });

    /*
    |-----------------------------------------------------------------------
    | Akses terbatas: Guru
    | Semua route guru berada di /api/admin/guru/...
    |-----------------------------------------------------------------------
    */
    Route::prefix('guru')->middleware(['role:Guru'])->group(function () {
        Route::get('data-siswa', [SiswaApiController::class, 'index']);
        Route::get('data-orangtua', [OrangtuaApiController::class, 'index']);
        Route::post('input-presensi', [PresensiController::class, 'store']);
        Route::post('input-presensi-mapel', [PresensiGuruMapelController::class, 'store']);
        Route::post('input-poin', [PoinSiswaController::class, 'store']);
        Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
        Route::post('change-password', [ProfilApiController::class, 'changePassword']);
    });

    /*
    |-----------------------------------------------------------------------
    | Akses terbatas: Siswa
    | Semua route siswa berada di /api/admin/siswa/...
    |-----------------------------------------------------------------------
    */
    Route::prefix('siswa')->middleware(['role:Siswa'])->group(function () {
        Route::get('presensi-saya', [PresensiApiController::class, 'index']);
        Route::get('poin-saya', [PoinSiswaApiController::class, 'index']);
        Route::get('jadwal', [JadwalProduktifApiController::class, 'index']);
        Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
        Route::post('change-password', [ProfilApiController::class, 'changePassword']);
    });

    /*
    |-----------------------------------------------------------------------
    | Akses terbatas: Orangtua
    | Semua route orangtua berada di /api/admin/ortu/...
    |-----------------------------------------------------------------------
    */
    Route::prefix('ortu')->middleware(['role:Orangtua'])->group(function () {
        Route::get('presensi-anak', [PresensiApiController::class, 'index']);
        Route::get('poin-anak', [PoinSiswaApiController::class, 'index']);
        Route::post('change-password', [ProfilApiController::class, 'changePassword']);
    });
});
