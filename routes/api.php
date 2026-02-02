<?php

use Illuminate\Support\Facades\Route;

Route::singularResourceParameters(false);

<<<<<<< HEAD
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
=======
/*
|--------------------------------------------------------------------------
| IMPORT CONTROLLERS
|--------------------------------------------------------------------------
*/

// 1. Public & Auth API
use App\Http\Controllers\Api\{
    BeritaApiController, PengumumanApiController,
    JurusanApiController, KurikulumApiController, KalenderApiController,
    MediaApiController, AlbumApiController, BannerApiController,
    FasilitasApiController, EkstrakurikulerApiController,
    PesanApiController, DataKontakApiController,
    PrestasiApiController, PortalApiController, PPDBLinkApiController,
    AuthApiController as ApiAuth, GuruApiController,
    DashboardApiController, SettingApiController,
    StrukturJabatanApiController, ProfilApiController,
};

// IMPORT AUTH CONTROLLER (Universal untuk semua akun)
use App\Http\Controllers\Auth\AuthController;

// 2. Admin & Shared Logic Controllers
use App\Http\Controllers\Admin\{
    BeritaController, PengumumanController,
    GuruController, JurusanController, KurikulumController, KalenderController,
    MediaController, AlbumController, BannerController, FasilitasController,
    EkstrakurikulerController, StrukturJabatanController,
    JabatanController, RoleController, UserController, PesanController, 
    LogAdminController, PrestasiController, ProfilSekolahController, 
    MapelController, SiswaController, OrangtuaController, 
    PresensiController as AdminPresensi, 
    JadwalProduktifController, PortalController, PpdbLinkController, 
    KelasController, TahunAjaranController, JamSekolahController, 
    GuruMapelController, DataKontakController, PresensiGuruMapelController, 
    SettingController, KenaikanKelasController,
};

// IMPORT KHUSUS PRESENSI ROLE BASED
use App\Http\Controllers\Guru\PresensiController as GuruPresensi;
use App\Http\Controllers\Siswa\PresensiController as SiswaPresensi;
use App\Http\Controllers\OrangTua\PresensiController as OrtuPresensi;
use App\Http\Controllers\Guru\PresensiGuruMapelController as GuruPresensiMapel;

// 3. Poin Siswa (Role Based Separation)
use App\Http\Controllers\Admin\PoinSiswaController as AdminPoin;
use App\Http\Controllers\Guru\PoinSiswaController as GuruPoin;
use App\Http\Controllers\Siswa\PoinSiswaController as SiswaPoin;
use App\Http\Controllers\Orangtua\PoinSiswaController as OrtuPoin;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
>>>>>>> master
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
<<<<<<< HEAD
    Route::get('mapel', [MapelApiController::class, 'index']);
=======
>>>>>>> master
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
<<<<<<< HEAD
| AUTH & SECURITY ROUTES -> /api/...
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.token'])->group(function () {
    Route::post('logout', [AdminAuth::class, 'logout']);
    Route::get('me', [AdminAuth::class, 'me']);
=======
| AUTH & SECURITY SHARED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth.token'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
>>>>>>> master
    Route::get('dashboard', [DashboardApiController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
<<<<<<< HEAD
| ADMIN ROUTES -> /api/admin/... (auth.token + role:Admin)
=======
| ADMIN ROUTES (FULL ACCESS)
>>>>>>> master
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth.token', 'role:Admin'])->group(function () {
    Route::get('logs', [LogAdminController::class, 'index']);
<<<<<<< HEAD

    Route::put('setting/general', [SettingController::class, 'updateGeneral']);
    Route::put('profil-sekolah', [ProfilSekolahController::class, 'update']);
    Route::get('api-kelas-list', [KelasApiController::class, 'index']);
    Route::get('api-setting-list', [SettingApiController::class, 'index']);
    Route::put('data-kontak', [DataKontakController::class, 'update']);
    Route::put('ppdb-link', [PpdbLinkController::class, 'update']);
    Route::put('jam-sekolah', [JamSekolahController::class, 'update']);
=======
    Route::put('setting/general', [SettingController::class, 'updateGeneral']);
    Route::put('profil-sekolah', [ProfilSekolahController::class, 'update']);
    Route::get('api-setting-list', [SettingApiController::class, 'index']);
    Route::put('data-kontak', [DataKontakController::class, 'update']);
    Route::put('ppdb-link', [PpdbLinkController::class, 'update']);
>>>>>>> master

    Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index']);
    Route::post('kenaikan-kelas/proses', [KenaikanKelasController::class, 'prosesMassal']);

    Route::apiResource('user', UserController::class);
    Route::apiResource('role', RoleController::class);
<<<<<<< HEAD
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
=======
    
    Route::post('siswa/import', [SiswaController::class, 'import']);
    Route::get('siswa/export', [SiswaController::class, 'export']);
    Route::apiResource('siswa', SiswaController::class);

    Route::post('orangtua/import', [OrangtuaController::class, 'import']);
    Route::get('orangtua/export', [OrangtuaController::class, 'export']);
    Route::apiResource('orangtua', OrangtuaController::class);

    Route::post('guru/import', [GuruController::class, 'import']);
    Route::get('guru/export', [GuruController::class, 'export']);
    Route::apiResource('guru', GuruController::class);

    // Guru Mapel (Import & Export)
    Route::post('guru-mapel/import', [GuruMapelController::class, 'import']);
    Route::get('guru-mapel/export', [GuruMapelController::class, 'export']);
    Route::apiResource('guru-mapel', GuruMapelController::class);

    Route::apiResource('jurusan', JurusanController::class);

    // Jam Sekolah (Import & Export)
    Route::post('jam-sekolah/import', [JamSekolahController::class, 'import']);
    Route::get('jam-sekolah/export', [JamSekolahController::class, 'export']);
    Route::apiResource('jam-sekolah', JamSekolahController::class);

    Route::apiResource('kurikulum', KurikulumController::class);
    Route::apiResource('kalender', KalenderController::class);
    
    Route::post('kelas/generate', [KelasController::class, 'generateFromPreviousYear']);
    Route::apiResource('kelas', KelasController::class);
    
    Route::apiResource('mapel', MapelController::class);
    Route::apiResource('tahun-ajaran', TahunAjaranController::class);
    Route::apiResource('jadwal-produktif', JadwalProduktifController::class);
    
    Route::get('presensi/export', [AdminPresensi::class, 'export']); 
    Route::apiResource('presensi', AdminPresensi::class); 
    
    Route::get('presensi-guru-mapel/export', [PresensiGuruMapelController::class, 'export']); 
    Route::get('presensi-guru-mapel/jadwal-hari-ini', [PresensiGuruMapelController::class, 'listJadwalHariIni']);
    Route::apiResource('presensi-guru-mapel', PresensiGuruMapelController::class);
    
    Route::get('poin-siswa/export', [AdminPoin::class, 'export']);
    Route::apiResource('poin-siswa', AdminPoin::class); 

>>>>>>> master
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
<<<<<<< HEAD
    
=======
>>>>>>> master
    Route::apiResource('pesan', PesanController::class)->except(['store']);
});

/*
|--------------------------------------------------------------------------
<<<<<<< HEAD
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
=======
| GURU & STAFF ROUTES (ROLE BASED JABATAN)
|--------------------------------------------------------------------------
*/
Route::prefix('guru')->middleware(['auth.token', 'role:guru'])->group(function () {

    Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
    Route::post('change-password', [ProfilApiController::class, 'changePassword']);
    
    Route::get('poin-siswa', [GuruPoin::class, 'index']); 
    Route::post('poin-siswa', [GuruPoin::class, 'store']); 

    Route::middleware(['jabatan:Waka Kurikulum'])->prefix('kurikulum')->group(function () {
        Route::get('mapel/export', [MapelController::class, 'export']);
        Route::apiResource('mapel', MapelController::class);
        Route::apiResource('kurikulum', KurikulumController::class);
        Route::apiResource('tahun-ajaran', TahunAjaranController::class);
        Route::apiResource('kalender', KalenderController::class);
        
        Route::post('kelas/generate', [KelasController::class, 'generateFromPreviousYear']);
        Route::get('kelas/export', [KelasController::class, 'export']);
        Route::apiResource('kelas', KelasController::class);
        
        Route::get('jam-sekolah/export', [JamSekolahController::class, 'export']);
        Route::apiResource('jam-sekolah', JamSekolahController::class);

        Route::get('guru-mapel/export', [GuruMapelController::class, 'export']);
        Route::apiResource('guru-mapel', GuruMapelController::class);

        Route::apiResource('jadwal-produktif', JadwalProduktifController::class);
    });

    Route::middleware(['jabatan:Waka Kesiswaan'])->prefix('kesiswaan')->group(function () {
        Route::get('siswa/export', [SiswaController::class, 'export']);
        Route::apiResource('siswa', SiswaController::class);
        
        Route::get('orangtua/export', [OrangtuaController::class, 'export']);
        Route::apiResource('orangtua', OrangtuaController::class);
        
        Route::apiResource('ekstrakurikuler', EkstrakurikulerController::class);

        Route::get('presensi/export', [AdminPresensi::class, 'export']); 
        Route::apiResource('presensi', AdminPresensi::class); 
        
        Route::get('presensi-guru-mapel/export', [PresensiGuruMapelController::class, 'export']); 
        Route::apiResource('presensi-guru-mapel', PresensiGuruMapelController::class);
        
        Route::get('poin-siswa/export', [AdminPoin::class, 'export']);
        Route::apiResource('poin-siswa', AdminPoin::class); 
        
        Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index']);
        Route::post('kenaikan-kelas/proses', [KenaikanKelasController::class, 'prosesMassal']);
    });

    Route::middleware(['jabatan:Waka Sarpras'])->prefix('sarpras')->group(function () {
        Route::apiResource('fasilitas', FasilitasController::class);
        Route::apiResource('album', AlbumController::class);
        Route::apiResource('media', MediaController::class);
    });

    Route::middleware(['jabatan:Waka Humas'])->prefix('humas')->group(function () {
        Route::apiResource('berita', BeritaController::class);
        Route::apiResource('pengumuman', PengumumanController::class);
        Route::apiResource('prestasi', PrestasiController::class);
        Route::apiResource('banner', BannerController::class);
        Route::apiResource('portal', PortalController::class);
        Route::apiResource('pesan', PesanController::class)->except(['store']);
        Route::put('ppdb-link', [PpdbLinkController::class, 'update']);
        Route::put('data-kontak', [DataKontakController::class, 'update']);
    });

    Route::middleware(['jabatan:Kepala Sekolah'])->prefix('kepsek')->group(function () {
        Route::put('profil-sekolah', [ProfilSekolahController::class, 'update']);
        Route::get('logs', [LogAdminController::class, 'index']);
        Route::apiResource('struktur-jabatan', StrukturJabatanController::class);
        
        Route::get('monitoring-presensi-harian/export', [AdminPresensi::class, 'export']);
        Route::get('monitoring-presensi-harian', [AdminPresensi::class, 'rekapHarianKepsek']);
        
        Route::get('monitoring-presensi-mapel/export', [PresensiGuruMapelController::class, 'export']); 
        Route::get('monitoring-presensi-mapel', [PresensiGuruMapelController::class, 'index']);
        
        Route::get('monitoring-poin-siswa/export', [AdminPoin::class, 'export']);
        Route::get('monitoring-poin-siswa', [AdminPoin::class, 'index']); 
    });

    Route::middleware(['jabatan:Ketua Jurusan'])->prefix('jurusan')->group(function () {
        Route::get('siswa/export', [SiswaController::class, 'export']);
        Route::get('siswa', [SiswaController::class, 'index']);
        Route::apiResource('mapel', MapelController::class);
        Route::apiResource('jadwal-produktif', JadwalProduktifController::class);
        Route::get('guru-mapel/export', [GuruMapelController::class, 'export']);
        Route::apiResource('guru-mapel', GuruMapelController::class);
    });

    Route::prefix('walikelas')->group(function () {
        Route::get('siswa/export', [SiswaController::class, 'export']);
        Route::get('data-siswa', [SiswaController::class, 'index']);
        Route::get('data-orangtua', [OrangtuaController::class, 'index']);
        Route::get('presensi/export', [GuruPresensi::class, 'export']); 
        Route::get('siswa-wali', [GuruPresensi::class, 'siswaWali']);
        Route::post('presensi', [GuruPresensi::class, 'store']); 
    });

    Route::prefix('mapel')->group(function () {
        Route::get('tugas-hari-ini', [GuruPresensiMapel::class, 'tugasHariIni']);
        Route::get('presensi/export', [GuruPresensiMapel::class, 'export']);
        Route::get('presensi', [GuruPresensiMapel::class, 'index']);
        Route::get('presensi/{id}', [GuruPresensiMapel::class, 'show']);
        Route::post('presensi', [GuruPresensiMapel::class, 'store']);
    });
>>>>>>> master
});

/*
|--------------------------------------------------------------------------
<<<<<<< HEAD
| SISWA ROUTES -> /api/siswa/... (auth.token + role:siswa)
|--------------------------------------------------------------------------
*/
Route::prefix('siswa')->middleware(['auth.token', 'role:siswa'])->group(function () {
    Route::get('presensi-saya', [PresensiApiController::class, 'index']);
    Route::get('poin-saya', [PoinSiswaApiController::class, 'index']);
    Route::get('jadwal', [JadwalProduktifApiController::class, 'index']);
=======
| SISWA ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('siswa')->middleware(['auth.token', 'role:siswa'])->group(function () {
    Route::get('presensi-saya', [SiswaPresensi::class, 'index']);
    Route::get('poin-saya', [SiswaPoin::class, 'index']); 
    Route::get('jadwal', [JadwalProduktifController::class, 'index']);
>>>>>>> master
    Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
    Route::post('change-password', [ProfilApiController::class, 'changePassword']);
});

/*
|--------------------------------------------------------------------------
<<<<<<< HEAD
| ORANGTUA ROUTES -> /api/ortu/... (auth.token + role:orangtua)
|--------------------------------------------------------------------------
*/
Route::prefix('ortu')->middleware(['auth.token', 'role:orangtua'])->group(function () {
    Route::get('presensi-anak', [PresensiApiController::class, 'index']);
    Route::get('poin-anak', [PoinSiswaApiController::class, 'index']);
=======
| ORANG TUA ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('ortu')->middleware(['auth.token', 'role:orangtua'])->group(function () {
    Route::get('list-anak', [OrtuPresensi::class, 'listAnak']);
    Route::get('presensi-anak', [OrtuPresensi::class, 'index']);
    Route::get('poin-anak', [OrtuPoin::class, 'index']); 
>>>>>>> master
    Route::post('change-password', [ProfilApiController::class, 'changePassword']);
});