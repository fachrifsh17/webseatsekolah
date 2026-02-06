<?php

use Illuminate\Support\Facades\Route;

Route::singularResourceParameters(false);

/*
|--------------------------------------------------------------------------
| 1. IMPORT CONTROLLERS
|--------------------------------------------------------------------------
*/

// --- Public & Auth API ---
use App\Http\Controllers\Api\{
    BeritaApiController, PengumumanApiController,
    JurusanApiController, KurikulumApiController, KalenderApiController,
    MediaApiController, AlbumApiController, BannerApiController,
    FasilitasApiController, EkstrakurikulerApiController,
    PesanApiController, DataKontakApiController,
    PrestasiApiController, PortalApiController, PPDBLinkApiController,
    AuthApiController as ApiAuth, GuruApiController,
    SettingApiController,
    StrukturJabatanApiController, ProfilApiController, JamSekolahApiController,
};
use App\Http\Controllers\Auth\AuthController;

// --- Admin & Shared Controllers ---
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
    PoinSiswaController as AdminPoin,
    DashboardController as AdminDashboard,
};

// --- Humas ---
use App\Http\Controllers\Humas\{
    BannerController as HumasBanner,
    BeritaController as HumasBerita,
    PengumumanController as HumasPengumuman,
    PesanController as HumasPesan,
    PortalController as HumasPortal,
    PpdbLinkController as HumasPpdb,
    PrestasiController as HumasPrestasi
};

// --- Kepala Sekolah ---
use App\Http\Controllers\KepalaSekolah\{
    LogAdminController as KepsekLog,
    PoinSiswaController as KepsekPoin,
    PresensiController as KepsekPresensi,
    PresensiGuruMapelController as KepsekPresensiMapel,
    ProfilSekolahController as KepsekProfil,
    SettingController as KepsekSetting
};

// --- Kesiswaan ---
use App\Http\Controllers\Kesiswaan\{
    EkstrakurikulerController as KesiswaanEskul,
    KenaikanKelasController as KesiswaanKenaikan,
    PoinSiswaController as KesiswaanPoin,
    PresensiGuruMapelController as KesiswaanPresensiMapel,
    PresensiController as KesiswaanPresensi,
    SiswaController as KesiswaanSiswa,
    OrangtuaController as KesiswaanOrtu
};

// --- Ketua Jurusan ---
use App\Http\Controllers\KetuaJurusan\{
    GuruMapelController as JurusanGuruMapel,
    JadwalProduktifController as JurusanJadwal,
    MapelController as JurusanMapel,
    SiswaController as JurusanSiswa
};

// --- Kurikulum ---
use App\Http\Controllers\Kurikulum\{
    GuruMapelController as KurikulumGuruMapel,
    JadwalProduktifController as KurikulumJadwal,
    JamSekolahController as KurikulumJam,
    KalenderController as KurikulumKalender,
    KurikulumController as KurikulumData,
    MapelController as KurikulumMapel
};

// --- Sarpras (Sarpas) ---
use App\Http\Controllers\Sarpas\{
    AlbumController as SarprasAlbum,
    FasilitasController as SarprasFasilitas,
    MediaController as SarprasMedia
};

// --- Wali Kelas ---
use App\Http\Controllers\Walikelas\{
    OrangtuaController as WaliOrtu,
    PresensiController as WaliPresensi,
    SiswaController as WaliSiswa
};

// --- Guru (General Role Based) ---
use App\Http\Controllers\Guru\{
    PresensiController as GuruPresensi,
    DashboardController as GuruDashboard,
    PresensiGuruMapelController as GuruPresensiMapel,
    PoinSiswaController as GuruPoin
};

// --- Siswa ---
use App\Http\Controllers\Siswa\{
    PresensiController as SiswaPresensi,
    JadwalProduktifController as SiswaJadwal,
    DashboardController as SiswaDashboard,
    JamSekolahController as SiswaJamSekolah,
    PoinSiswaController as SiswaPoin
};

// --- Orang Tua ---
use App\Http\Controllers\Orangtua\{
    PresensiController as OrtuPresensi,
    PoinSiswaController as OrtuPoin,
    DashboardController as OrtuDashboard
};

/*
|--------------------------------------------------------------------------
| 2. PUBLIC API ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('public')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('login-api', [ApiAuth::class, 'login']);
    Route::get('jamsekolah', [JamSekolahApiController::class, 'index']);
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
| 3. AUTHENTICATED SHARED ROUTES (ME & LOGOUT)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth.token'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
});

/*
|--------------------------------------------------------------------------
| 4. ADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->middleware(['auth.token', 'role:Admin'])->group(function () {
    // Profil Self-Service Admin
    Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
    Route::post('change-password', [ProfilApiController::class, 'changePassword']);

    Route::get('dashboard',[AdminDashboard::class,'index']);
    Route::get('logs', [LogAdminController::class, 'index']);
    Route::put('setting/general', [SettingController::class, 'updateGeneral']);
    Route::put('profil-sekolah', [ProfilSekolahController::class, 'update']);
    Route::get('api-setting-list', [SettingApiController::class, 'index']);
    Route::put('data-kontak', [DataKontakController::class, 'update']);
    Route::put('ppdb-link', [PpdbLinkController::class, 'update']);
    Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index']);
    Route::post('kenaikan-kelas/proses', [KenaikanKelasController::class, 'prosesMassal']);
    
    Route::apiResource('user', UserController::class);
    Route::apiResource('role', RoleController::class);
    
    Route::post('siswa/import', [SiswaController::class, 'import']);
    Route::get('siswa/export', [SiswaController::class, 'export']);
    Route::apiResource('siswa', SiswaController::class);
    
    Route::post('orangtua/import', [OrangtuaController::class, 'import']);
    Route::get('orangtua/export', [OrangtuaController::class, 'export']);
    Route::apiResource('orangtua', OrangtuaController::class);
    
    Route::post('guru/import', [GuruController::class, 'import']);
    Route::get('guru/export', [GuruController::class, 'export']);
    Route::apiResource('guru', GuruController::class);
    
    Route::post('guru-mapel/import', [GuruMapelController::class, 'import']);
    Route::get('guru-mapel/export', [GuruMapelController::class, 'export']);
    Route::apiResource('guru-mapel', GuruMapelController::class);
    
    Route::apiResource('jurusan', JurusanController::class);
    
    Route::post('jam-sekolah/import', [JamSekolahController::class, 'import']);
    Route::get('jam-sekolah/export', [JamSekolahController::class, 'export']);
    Route::apiResource('jam-sekolah', JamSekolahController::class);
    
    Route::apiResource('kurikulum', KurikulumController::class);
    Route::apiResource('kalender', KalenderController::class);
    
    Route::post('kelas/import', [KelasController::class, 'import']);
    Route::get('kelas/export', [KelasController::class, 'export']);
    Route::post('kelas/generate', [KelasController::class, 'generateFromPreviousYear']);
    Route::apiResource('kelas', KelasController::class);
    
    Route::get('mapel/export', [MapelController::class, 'export']);
    Route::post('mapel/import', [MapelController::class, 'import']);
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
| 5. GURU & STAFF ROUTES (BY JABATAN)
|--------------------------------------------------------------------------
*/

Route::prefix('guru')->middleware(['auth.token', 'role:guru'])->group(function () {
    
    // --- Guru General ---
    Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
    Route::post('change-password', [ProfilApiController::class, 'changePassword']);
    Route::get('dashboard', [GuruDashboard::class, 'index']);
    Route::get('poin-siswa', [GuruPoin::class, 'index']); 
    Route::post('poin-siswa', [GuruPoin::class, 'store']); 

    // --- Jabatan: Waka Kurikulum ---
    Route::middleware(['jabatan:Waka Kurikulum'])->prefix('kurikulum')->group(function () {
        // Mapel
        Route::post('mapel/import', [KurikulumMapel::class, 'import']);
        Route::get('mapel/export', [KurikulumMapel::class, 'export']);
        Route::apiResource('mapel', KurikulumMapel::class);

        // Jam Sekolah
        Route::post('jam-sekolah/import', [KurikulumJam::class, 'import']);
        Route::get('jam-sekolah/export', [KurikulumJam::class, 'export']);
        Route::apiResource('jam-sekolah', KurikulumJam::class);

        // Guru Mapel
        Route::post('guru-mapel/import', [KurikulumGuruMapel::class, 'import']);
        Route::get('guru-mapel/export', [KurikulumGuruMapel::class, 'export']);
        Route::apiResource('guru-mapel', KurikulumGuruMapel::class);

        // Lainnya
        Route::apiResource('kurikulum', KurikulumData::class);
        Route::apiResource('kalender', KurikulumKalender::class);
        Route::apiResource('jadwal-produktif', KurikulumJadwal::class);
    });

    // --- Jabatan: Waka Kesiswaan ---
    Route::middleware(['jabatan:Waka Kesiswaan'])->prefix('kesiswaan')->group(function () {
        // Siswa
        Route::get('siswa/export', [KesiswaanSiswa::class, 'export']); 
        Route::get('siswa', [KesiswaanSiswa::class, 'index']);
        Route::get('siswa/{siswa}', [KesiswaanSiswa::class, 'show']);
        Route::put('siswa/{siswa}', [KesiswaanSiswa::class, 'update']);

        // Orang Tua
        Route::get('orangtua/export', [KesiswaanOrtu::class, 'export']);
        Route::get('orangtua', [KesiswaanOrtu::class, 'index']);
        Route::get('orangtua/{orangtua}', [KesiswaanOrtu::class, 'show']);
        Route::put('orangtua/{orangtua}', [KesiswaanOrtu::class, 'update']);

        Route::apiResource('ekstrakurikuler', KesiswaanEskul::class);

        // Presensi Harian Siswa
        Route::get('presensi/export', [KesiswaanPresensi::class, 'export']); 
        Route::get('presensi', [KesiswaanPresensi::class, 'index']);
        Route::get('presensi/{id}', [KesiswaanPresensi::class, 'show']);
        Route::put('presensi/{id}', [KesiswaanPresensi::class, 'update']); 

        // Presensi Guru Mapel
        Route::get('presensi-guru-mapel/export', [KesiswaanPresensiMapel::class, 'export']);
        Route::get('presensi-guru-mapel/jadwal-hari-ini', [KesiswaanPresensiMapel::class, 'listJadwalHariIni']);
        Route::get('presensi-guru-mapel', [KesiswaanPresensiMapel::class, 'index']);
        Route::get('presensi-guru-mapel/{id}', [KesiswaanPresensiMapel::class, 'show']);
        Route::put('presensi-guru-mapel/{id}', [KesiswaanPresensiMapel::class, 'update']);

        // Poin Siswa & Kenaikan
        Route::get('poin-siswa/export', [KesiswaanPoin::class, 'export']);
        Route::apiResource('poin-siswa', KesiswaanPoin::class); 
        Route::apiResource('kenaikan-kelas', KesiswaanKenaikan::class);
    });

    // --- Jabatan: Waka Sarpras ---
    Route::middleware(['jabatan:Waka Sarpras'])->prefix('sarpras')->group(function () {
        Route::apiResource('fasilitas', SarprasFasilitas::class);
        Route::apiResource('album', SarprasAlbum::class);
        Route::apiResource('media', SarprasMedia::class);
    });

    // --- Jabatan: Waka Humas ---
    Route::middleware(['jabatan:Waka Humas'])->prefix('humas')->group(function () {
        Route::apiResource('berita', HumasBerita::class);
        Route::apiResource('pengumuman', HumasPengumuman::class);
        Route::apiResource('prestasi', HumasPrestasi::class);
        Route::apiResource('banner', HumasBanner::class);
        Route::apiResource('portal', HumasPortal::class);
        Route::apiResource('pesan', HumasPesan::class)->except(['store']);
        Route::put('ppdb-link', [HumasPpdb::class, 'update']);
    });

    // --- Jabatan: Kepala Sekolah ---
    Route::middleware(['jabatan:Kepala Sekolah'])->prefix('kepsek')->group(function () {
        Route::put('profil-sekolah', [KepsekProfil::class, 'update']);
        Route::get('logs', [KepsekLog::class, 'index']);
        Route::put('setting/general', [KepsekSetting::class, 'updateGeneral']);
        Route::get('monitoring-presensi-harian', [KepsekPresensi::class, 'rekapHarianKepsek']);
        Route::get('monitoring-presensi-mapel', [KepsekPresensiMapel::class, 'index']);
        Route::get('monitoring-poin-siswa', [KepsekPoin::class, 'index']); 
        Route::get('presensi-mapel/export', [KepsekPresensiMapel::class, 'export']);
        Route::get('presensi/export', [KepsekPresensi::class, 'export']); 
        Route::get('poin-siswa/export', [KepsekPoin::class, 'export']);
    });

    // --- Jabatan: Ketua Jurusan ---
    Route::middleware(['jabatan:Ketua Jurusan'])->prefix('jurusan')->group(function () {
        Route::get('siswa', [JurusanSiswa::class, 'index']);
        Route::get('siswa/export', [JurusanSiswa::class, 'export']);
        Route::get('mapel', [JurusanMapel::class, 'index']);
        Route::get('mapel/export', [JurusanMapel::class, 'export']);
        Route::get('mapel/{id}', [JurusanMapel::class, 'show']);
        Route::get('guru-mapel', [JurusanGuruMapel::class, 'index']);
        Route::get('guru-mapel/export', [JurusanGuruMapel::class, 'export']);
        Route::get('guru-mapel/{id}', [JurusanGuruMapel::class, 'show']);
        Route::apiResource('jadwal-produktif', JurusanJadwal::class);
    });

    // --- Jabatan: Wali Kelas ---
    Route::prefix('walikelas')->group(function () {
        Route::get('data-siswa', [WaliSiswa::class, 'index']);
        Route::get('data-orangtua', [WaliOrtu::class, 'index']);
        Route::post('presensi', [WaliPresensi::class, 'store']); 
        Route::get('siswa-wali', [WaliSiswa::class, 'index']); 
        Route::get('siswa-export', [WaliSiswa::class, 'export']); 
        Route::get('orangtua-export', [WaliOrtu::class, 'export']); 
        Route::get('export', [WaliPresensi::class, 'export']);
    });

    // --- Guru Mapel General ---
    Route::prefix('mapel')->group(function () {
        Route::get('tugas-hari-ini', [GuruPresensiMapel::class, 'tugasHariIni']);
        Route::get('presensi/export', [GuruPresensiMapel::class, 'export']);
        Route::get('presensi', [GuruPresensiMapel::class, 'index']);
        Route::get('presensi/{id}', [GuruPresensiMapel::class, 'show']);
        Route::post('presensi', [GuruPresensiMapel::class, 'store']);
    });
});

/*
|--------------------------------------------------------------------------
| 6. SISWA & ORANG TUA ROUTES
|--------------------------------------------------------------------------
*/

// --- Role: Siswa ---
Route::prefix('siswa')->middleware(['auth.token', 'role:siswa'])->group(function () {
    Route::get('dashboard', [SiswaDashboard::class, 'index']);
    Route::get('presensi-saya', [SiswaPresensi::class, 'index']);
    Route::get('poin-saya', [SiswaPoin::class, 'index']); 
    Route::get('jadwal', [SiswaJadwal::class, 'index']);
    Route::get('jam-sekolah', [SiswaJamSekolah::class, 'index']);
    Route::get('jam-sekolah/export', [SiswaJamSekolah::class, 'export']);
    Route::post('update-foto', [ProfilApiController::class, 'updateFoto']);
    Route::post('change-password', [ProfilApiController::class, 'changePassword']);
});

// --- Role: Orang Tua ---
Route::prefix('ortu')->middleware(['auth.token', 'role:orangtua'])->group(function () {
    Route::get('dashboard', [OrtuDashboard::class, 'index']);
    Route::get('list-anak', [OrtuPresensi::class, 'listAnak']);
    Route::get('presensi-anak', [OrtuPresensi::class, 'index']);
    Route::get('poin-anak', [OrtuPoin::class, 'index']); 
    Route::post('change-password', [ProfilApiController::class, 'changePassword']);
});