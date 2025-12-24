<?php

use Illuminate\Support\Facades\Route;

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
    Route::get('profil-sekolah', [ProfilSekolahController::class, 'index']);
    Route::get('struktur', [StrukturJabatanApiController::class, 'index']);
    Route::post('pesan', [PesanApiController::class, 'store']);
});

Route::middleware(['auth.token'])->group(function () {
    Route::post('logout', [AdminAuth::class, 'logout']);
    
    Route::get('dashboard', [DashboardApiController::class, 'index']);

    Route::get('me', [AdminAuth::class, 'me']);
    Route::get('me-api', [AdminAuth::class, 'me']);

    Route::middleware(['role:Admin'])->prefix('admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
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
        Route::apiResource('berita', BeritaController::class);
        Route::apiResource('pengumuman', PengumumanController::class);
        Route::apiResource('prestasi', PrestasiController::class);
        Route::apiResource('fasilitas', FasilitasController::class);
        Route::apiResource('ekstrakurikuler', EkstrakurikulerController::class);
        Route::apiResource('banner', BannerController::class);
        Route::apiResource('album', AlbumController::class);
        Route::apiResource('media', MediaController::class);
        Route::apiResource('portal', PortalController::class);
        Route::apiResource('ppdb-link', PpdbLinkController::class);
        Route::apiResource('pesan', PesanController::class)->except(['store']);
        Route::apiResource('struktur-jabatan', StrukturJabatanController::class);
        Route::apiResource('data-kontak', DataKontakController::class);
        
        Route::get('logs', [LogAdminController::class, 'index']);
        Route::put('profil-sekolah', [ProfilSekolahController::class, 'update']);
        Route::post('setting/update', [SettingController::class, 'updateGeneral']);
        Route::get('api-kelas-list', [KelasApiController::class, 'index']);
        Route::get('api-setting-list', [SettingApiController::class, 'index']);
    });

    Route::middleware(['role:Guru'])->prefix('guru')->group(function () {
        Route::get('data-siswa', [SiswaApiController::class, 'index']);
        Route::get('data-orangtua', [OrangtuaApiController::class, 'index']);
        Route::post('input-presensi', [PresensiController::class, 'store']);
        Route::post('input-presensi-mapel', [PresensiGuruMapelController::class, 'store']);
        Route::post('input-poin', [PoinSiswaController::class, 'store']);
        Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
        Route::post('change-password', [ProfilApiController::class, 'changePassword']);
    });

    Route::middleware(['role:Siswa'])->prefix('siswa')->group(function () {
        Route::get('presensi-saya', [PresensiApiController::class, 'index']);
        Route::get('poin-saya', [PoinSiswaApiController::class, 'index']);
        Route::get('jadwal', [JadwalProduktifApiController::class, 'index']);
        Route::get('tahun-ajaran', [TahunAjaranApiController::class, 'index']);
        Route::get('jam-sekolah', [JamSekolahApiController::class, 'index']);
        Route::get('buku-poin', [SettingApiController::class, 'index']); 
        Route::get('no-kesiswaan', [SettingApiController::class, 'index']); 
        Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
        Route::post('change-password', [ProfilApiController::class, 'changePassword']);
    });

    Route::middleware(['role:Orangtua'])->prefix('ortu')->group(function () {
        Route::get('presensi-anak', [PresensiApiController::class, 'index']);
        Route::get('poin-anak', [PoinSiswaApiController::class, 'index']);
        Route::get('buku-poin', [SettingApiController::class, 'index']);
        Route::post('change-password', [ProfilApiController::class, 'changePassword']);
    });
});