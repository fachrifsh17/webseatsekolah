<?php

use Illuminate\Support\Facades\Route;

Route::singularResourceParameters(false);

use App\Http\Controllers\Api\{
    BeritaApiController, PengumumanApiController, GuruApiController,
    JurusanApiController, KurikulumApiController, KalenderApiController,
    MediaApiController, AlbumApiController, BannerApiController,
    FasilitasApiController, EkstrakurikulerApiController,
    PesanApiController, DataKontakApiController, MapelApiController,
    SiswaApiController, OrangtuaApiController, PresensiApiController,
    JadwalProduktifApiController, PrestasiApiController, PoinSiswaApiController,
    PortalApiController, PPDBLinkApiController, KelasApiController,
    AuthApiController as ApiAuth,
    DashboardApiController, SettingApiController,
    StrukturJabatanApiController, ProfilApiController
};

use App\Http\Controllers\Admin\{
    AuthController as AdminAuth, BeritaController, PengumumanController,
    GuruController, JurusanController, KurikulumController, KalenderController,
    MediaController, AlbumController, BannerController, FasilitasController,
    EkstrakurikulerController, StrukturJabatanController,
    JabatanController,
    RoleController, UserController, PesanController, LogAdminController,
    PrestasiController, ProfilSekolahController, MapelController,
    SiswaController, OrangtuaController, PresensiController,
    JadwalProduktifController, PoinSiswaController, PortalController,
    PpdbLinkController, KelasController, TahunAjaranController,
    JamSekolahController, GuruMapelController, DataKontakController,
    PresensiGuruMapelController, SettingController,
    KenaikanKelasController,
};

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES -> /api/public/...
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::post('login', [AdminAuth::class, 'login']);
    Route::post('login-api', [ApiAuth::class, 'login']);
    Route::post('register', [ApiAuth::class, 'register']);

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
    Route::get('setting', [SettingApiController::class, 'index']);
    Route::post('pesan', [PesanApiController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| AUTH & SECURITY ROUTES -> /api/...
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.token'])->group(function () {
    Route::post('logout', [AdminAuth::class, 'logout']);
    Route::get('me', [AdminAuth::class, 'me']);
    Route::get('dashboard', [DashboardApiController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES -> /api/admin/... (auth.token + role:Admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth.token', 'role:Admin'])->group(function () {
    Route::get('logs', [LogAdminController::class, 'index']);

    Route::put('setting/general', [SettingController::class, 'updateGeneral']);
    Route::put('profil-sekolah', [ProfilSekolahController::class, 'update']);
    Route::get('api-kelas-list', [KelasApiController::class, 'index']);
    Route::get('api-setting-list', [SettingApiController::class, 'index']);
    Route::put('data-kontak', [DataKontakController::class, 'update']);
    Route::put('ppdb-link', [PpdbLinkController::class, 'update']);
    Route::put('jam-sekolah', [JamSekolahController::class, 'update']);

    Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index']);
    Route::post('kenaikan-kelas/proses', [KenaikanKelasController::class, 'prosesMassal']);

    Route::apiResource('user', UserController::class);
    Route::apiResource('role', RoleController::class);
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
    Route::apiResource('jadwal-produktif', JadwalProduktifController::class);
    Route::apiResource('presensi', PresensiController::class);
    Route::apiResource('presensi-guru-mapel', PresensiGuruMapelController::class);
    Route::apiResource('poin-siswa', PoinSiswaController::class);
    Route::apiResource('portal', PortalController::class);
    Route::apiResource('berita', BeritaController::class);
    Route::apiResource('pengumuman', PengumumanController::class);
    Route::apiResource('prestasi', PrestasiController::class);
    Route::apiResource('fasilitas', FasilitasController::class);
    Route::apiResource('ekstrakurikuler', EkstrakurikulerController::class);
    Route::apiResource('banner', BannerController::class);
    Route::apiResource('album', AlbumController::class);
    Route::apiResource('media', MediaController::class);
    Route::apiResource('jabatan', JabatanController::class);
    Route::apiResource('struktur-jabatan', StrukturJabatanController::class);
    
    Route::apiResource('pesan', PesanController::class)->except(['store']);
});

/*
|--------------------------------------------------------------------------
| GURU ROUTES -> /api/guru/... (auth.token + role:guru)
|--------------------------------------------------------------------------
*/
Route::prefix('guru')->middleware(['auth.token', 'role:guru'])->group(function () {
    Route::get('data-siswa', [SiswaApiController::class, 'index']);
    Route::get('data-orangtua', [OrangtuaApiController::class, 'index']);
    Route::get('siswa-wali', [PresensiController::class, 'siswaWali']);

    Route::get('presensi', [PresensiController::class, 'index']);
    Route::get('presensi/{presensi}', [PresensiController::class, 'show']);
    Route::post('presensi', [PresensiController::class, 'store']);
    Route::put('presensi/{presensi}', [PresensiController::class, 'update']);
    Route::delete('presensi/{presensi}', [PresensiController::class, 'destroy']);

    Route::get('presensi-mapel', [PresensiGuruMapelController::class, 'index']);
    Route::get('presensi-mapel/{presensi}', [PresensiGuruMapelController::class, 'show']);
    Route::post('presensi-mapel', [PresensiGuruMapelController::class, 'store']);

    Route::get('poin', [PoinSiswaController::class, 'index']);
    Route::get('poin/{poinSiswa}', [PoinSiswaController::class, 'show']);
    Route::post('poin', [PoinSiswaController::class, 'store']);

    Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
    Route::post('change-password', [ProfilApiController::class, 'changePassword']);
});

/*
|--------------------------------------------------------------------------
| SISWA ROUTES -> /api/siswa/... (auth.token + role:siswa)
|--------------------------------------------------------------------------
*/
Route::prefix('siswa')->middleware(['auth.token', 'role:siswa'])->group(function () {
    Route::get('presensi-saya', [PresensiApiController::class, 'index']);
    Route::get('poin-saya', [PoinSiswaApiController::class, 'index']);
    Route::get('jadwal', [JadwalProduktifApiController::class, 'index']);
    Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
    Route::post('change-password', [ProfilApiController::class, 'changePassword']);
});

/*
|--------------------------------------------------------------------------
| ORANGTUA ROUTES -> /api/ortu/... (auth.token + role:orangtua)
|--------------------------------------------------------------------------
*/
Route::prefix('ortu')->middleware(['auth.token', 'role:orangtua'])->group(function () {
    Route::get('presensi-anak', [PresensiApiController::class, 'index']);
    Route::get('poin-anak', [PoinSiswaApiController::class, 'index']);
    Route::post('change-password', [ProfilApiController::class, 'changePassword']);
});