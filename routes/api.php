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
    LoginApiController as ApiAuth, GuruApiController,
    SettingApiController,
    StrukturJabatanApiController, ProfilApiController, JamSekolahApiController,
};
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ProfilController;

// --- Admin & Shared Controllers ---
use App\Http\Controllers\Admin\{
    BeritaController, PengumumanController,
    GuruController, JurusanController, KurikulumController, KalenderController,
    MediaController, AlbumController, BannerController, FasilitasController,
    EkstrakurikulerController, StrukturJabatanController,
    JabatanController, RoleController, UserController, PesanController, 
    LogAktivitasController, PrestasiController, ProfilSekolahController, 
    MapelController, SiswaController, OrangtuaController, 
    PresensiController as AdminPresensi, 
    PortalController, PpdbLinkController, 
    KelasController, TahunAjaranController, JamSekolahController, 
    GuruMapelController, DataKontakController, PresensiGuruMapelController, 
    SettingController, KenaikanKelasController,
    PoinSiswaController as AdminPoin,
    DashboardController as AdminDashboard,
    KelasWaliKelasController,
    TingkatanController,SemesterController,
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
    LogAktivitasController as KepsekLog,
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
    MapelController as JurusanMapel,
    SiswaController as JurusanSiswa,
    KelasController as JurusanKelas
};

// --- Kurikulum ---
use App\Http\Controllers\Kurikulum\{
    GuruMapelController as KurikulumGuruMapel,
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
    PoinSiswaController as GuruPoin,
    JamSekolahController as GuruJamSekolah
};

// --- Siswa ---
use App\Http\Controllers\Siswa\{
    PresensiController as SiswaPresensi,
    DashboardController as SiswaDashboard,
    JamSekolahController as SiswaJamSekolah,
    PoinSiswaController as SiswaPoin,
    GuruMapelController as SiswaMapel
};

// --- Orang Tua ---
use App\Http\Controllers\Orangtua\{
    PresensiController as OrtuPresensi,
    PoinSiswaController as OrtuPoin,
    DashboardController as OrtuDashboard,
    JamSekolahController as OrtuJamSekolah
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
    Route::get('media/{id}', [MediaApiController::class, 'show']);
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
    Route::post('update-foto', [ProfilController::class, 'updateFoto']);
    Route::post('change-password', [ProfilController::class, 'changePassword']);
    Route::post('switch-role', [AuthController::class, 'switchRole']);
});

/*
|--------------------------------------------------------------------------
| 4. ADMIN ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::get('struktur-jabatan/ttd/{id}', [StrukturJabatanController::class, 'showTtd']);
});

Route::prefix('admin')->name('admin.')->middleware(['auth.token', 'role:Admin'])->group(function () {
    // Profil Self-Service Admin
     

    Route::get('dashboard',[AdminDashboard::class,'index']);
    Route::get('log', [LogAktivitasController::class, 'index']);
    Route::get('setting/general', [SettingController::class, 'index']);
    Route::post('setting/general', [SettingController::class, 'updateGeneral']);
    Route::get('profil-sekolah', [ProfilSekolahController::class, 'index']);
    Route::post('profil-sekolah', [ProfilSekolahController::class, 'update']);
    Route::get('api-setting-list', [SettingApiController::class, 'index']);
    Route::get('data-kontak', [DataKontakController::class, 'index']);
    Route::put('data-kontak', [DataKontakController::class, 'update']);
    Route::get('ppdb-link', [PpdbLinkController::class, 'index']);
    Route::put('ppdb-link', [PpdbLinkController::class, 'update']);
    Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index']);
    Route::post('kelas/generate', [KenaikanKelasController::class, 'generateFromPreviousYear']);
    Route::post('kenaikan-kelas/proses', [KenaikanKelasController::class, 'prosesMassal']);
    Route::post('walikelas/kelas-copy', [KelasWaliKelasController::class, 'cloneToNewYear']);
    Route::post('walikelas/naik-tingkat-kelas', [KelasWaliKelasController::class, 'bulkUpdateTingkat']);
    Route::post('walikelas/kelas-create', [KelasWaliKelasController::class, 'prepareNewYear']);
    Route::post('media/mass-destroy', [MediaController::class, 'massDestroy']);
    Route::apiResource('kelaswalikelas', KelasWaliKelasController::class,);
    Route::apiResource('tingkatan', TingkatanController::class,);
    
    Route::post('user/bulk-delete', [UserController::class, 'bulkDelete']);
    Route::apiResource('user', UserController::class);
    Route::apiResource('role', RoleController::class);
    
    Route::post('siswa/import', [SiswaController::class, 'import']);
    Route::get('siswa/export', [SiswaController::class, 'export']);
    Route::post('siswa/import-preview', [SiswaController::class, 'importPreview']);
    Route::post('siswa/bulk-delete', [SiswaController::class, 'bulkDelete']);
    Route::apiResource('siswa', SiswaController::class);
    
    Route::post('orangtua/import', [OrangtuaController::class, 'import']);
    Route::get('orangtua/export', [OrangtuaController::class, 'export']);
    Route::post('orangtua/bulk-delete', [OrangtuaController::class, 'bulkDelete']);
    Route::post('orangtua/import-preview', [OrangtuaController::class, 'importPreview']);
    Route::apiResource('orangtua', OrangtuaController::class);
    
    Route::post('guru/import', [GuruController::class, 'import']);
    Route::get('guru/export', [GuruController::class, 'export']);
    Route::post('guru/import-preview', [GuruController::class, 'importPreview']);
    Route::post('guru/bulk-delete', [GuruController::class, 'bulkDelete']);
    Route::apiResource('guru', GuruController::class);
    
    Route::get('guru-mapel/jam-by-hari', [GuruMapelController::class, 'getJamByHari']);
    Route::post('guru-mapel/import', [GuruMapelController::class, 'import']);
    Route::get('guru-mapel/export', [GuruMapelController::class, 'export']);
    Route::post('guru-mapel/import-preview', [GuruMapelController::class, 'importPreview']);
    Route::delete('guru-mapel/bulk-delete', [GuruMapelController::class, 'bulkDestroy']);
    Route::apiResource('guru_mapel', GuruMapelController::class);
    
    Route::apiResource('jurusan', JurusanController::class);
    
    Route::post('jam-sekolah/import-preview', [JamSekolahController::class, 'importPreview']);
    Route::post('jam-sekolah/bulk-delete', [JamSekolahController::class, 'bulkDelete']);
    Route::post('jam-sekolah/import', [JamSekolahController::class, 'import']);
    Route::get('jam-sekolah/export', [JamSekolahController::class, 'export']);
    Route::apiResource('jam_sekolah', JamSekolahController::class);
    
    Route::apiResource('kurikulum', KurikulumController::class);
    Route::apiResource('kalender', KalenderController::class);
    
    Route::post('kelas/import', [KelasController::class, 'import']);
    Route::get('kelas/export', [KelasController::class, 'export']);
    Route::post('kelas/import-preview', [KelasController::class, 'importPreview']);
    Route::post('kelas/bulk-delete', [KelasController::class, 'bulkDelete']);
    Route::apiResource('kelas', KelasController::class);
    
    Route::get('mapel/export', [MapelController::class, 'export']);
    Route::post('mapel/import', [MapelController::class, 'import']);
    Route::post('mapel/import-preview', [MapelController::class, 'importPreview']);
    Route::delete('mapel/bulk-delete', [MapelController::class, 'destroyBulk']);
    Route::apiResource('mapel', MapelController::class);
    
    Route::get('presensi/export', [AdminPresensi::class, 'export']); 
    Route::get('list-kelas', [AdminPresensi::class, 'listKelas']);
    Route::get('list-siswa/{kelas_id}', [AdminPresensi::class, 'listSiswaPresensi']);
    Route::apiResource('presensi', AdminPresensi::class); 
    
    Route::get('presensi-guru-mapel/export', [PresensiGuruMapelController::class, 'export']); 
    Route::get('jadwal-hari-ini', [PresensiGuruMapelController::class, 'listJadwalHariIni']);
    Route::get('siswa-by-jadwal/{id}', [PresensiGuruMapelController::class, 'getSiswaByJadwal']);
    Route::apiResource('presensi-guru-mapel', PresensiGuruMapelController::class);
    
    Route::get('poin-siswa/export', [AdminPoin::class, 'export']);
    Route::apiResource('poin_siswa', AdminPoin::class); 
    
    Route::post('pesan/mark-all-read', [PesanController::class, 'markAllAsRead']);
    Route::patch('pesan/{pesan}/status', [PesanController::class, 'updateStatus']);
    Route::apiResource('pesan', PesanController::class)->except(['store']);

    Route::apiResource('tahun_ajaran', TahunAjaranController::class);
    Route::apiResource('semester', SemesterController::class);
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
    Route::apiResource('struktur_jabatan', StrukturJabatanController::class);
    
});

/*
|--------------------------------------------------------------------------
| 5. GURU & STAFF ROUTES (BY JABATAN)
|--------------------------------------------------------------------------
*/

Route::prefix('guru')->name('guru.')->middleware(['auth.token', 'role:guru'])->group(function () {
    
    // --- Guru General ---
     
    Route::get('dashboard', [GuruDashboard::class, 'index']);
    
    Route::get('jam-sekolah/export', [GuruJamSekolah::class, 'export']);
    Route::apiResource('jam_sekolah', GuruJamSekolah::class);

    Route::apiResource('poin_siswa', GuruPoin::class); 

    // --- Jabatan: Waka Kurikulum ---
    Route::middleware(['jabatan:Waka Kurikulum'])->prefix('kurikulum')->name('kurikulum.')->group(function () {
        // Mapel
        Route::get('mapel/export', [KurikulumMapel::class, 'export']);
        Route::post('mapel/import', [KurikulumMapel::class, 'import']);
        Route::post('mapel/import-preview', [KurikulumMapel::class, 'importPreview']);
        Route::delete('mapel/bulk-delete', [KurikulumMapel::class, 'destroyBulk']);
        Route::apiResource('mapel', KurikulumMapel::class);

        // Jam Sekolah
        Route::post('jam-sekolah/import-preview', [KurikulumJam::class, 'importPreview']);
        Route::post('jam-sekolah/bulk-delete', [KurikulumJam::class, 'bulkDelete']);
        Route::post('jam-sekolah/import', [KurikulumJam::class, 'import']);
        Route::get('jam-sekolah/export', [KurikulumJam::class, 'export']);
        Route::apiResource('jam_sekolah', KurikulumJam::class);

        // Guru Mapel
        Route::post('guru-mapel/import', [KurikulumGuruMapel::class, 'import']);
        Route::get('guru-mapel/export', [KurikulumGuruMapel::class, 'export']);
        Route::apiResource('guru_mapel', KurikulumGuruMapel::class);

        // Lainnya
        Route::apiResource('kurikulum', KurikulumData::class);
        Route::apiResource('kalender', KurikulumKalender::class);
    });

    // --- Jabatan: Waka Kesiswaan ---
    Route::middleware(['jabatan:Waka Kesiswaan'])->prefix('kesiswaan')->name('kesiswaan.')->group(function () {
        // Siswa
        Route::get('siswa/export', [KesiswaanSiswa::class, 'export']); 
        Route::apiResource('siswa', KesiswaanSiswa::class);

        // Orang Tua
        Route::get('orangtua/export', [KesiswaanOrtu::class, 'export']);;
        Route::apiResource('orangtua', KesiswaanOrtu::class);

        Route::apiResource('ekstrakurikuler', KesiswaanEskul::class);

        // Presensi Harian Siswa
        Route::get('presensi/export', [KesiswaanPresensi::class, 'export']); 
        Route::get('list-kelas', [KesiswaanPresensi::class, 'listKelas']);
        Route::get('list-siswa', [KesiswaanPresensi::class, 'listSiswaPresensi']);
        Route::apiResource('presensi', KesiswaanPresensi::class);

        // Presensi Guru Mapel
        Route::get('presensi-mapel/export', [KesiswaanPresensiMapel::class, 'export']);
        Route::get('presensi-mapel/jadwal-hari-ini', [KesiswaanPresensiMapel::class, 'listJadwalHariIni']);
        Route::get('siswa-by-jadwal/{id}', [KesiswaanPresensiMapel::class, 'getSiswaByJadwal']);
        Route::apiResource('presensi-mapel', KesiswaanPresensiMapel::class);

        // Poin Siswa & Kenaikan
        Route::get('poin-siswa/export', action: [KesiswaanPoin::class, 'export']);
        Route::apiResource('poin_siswa', KesiswaanPoin::class); 
        Route::get('kenaikan-kelas', [KesiswaanKenaikan::class, 'index']);
        Route::post('kelas/generate', [KesiswaanKenaikan::class, 'generateFromPreviousYear']);
        Route::post('kenaikan-kelas/proses', [KesiswaanKenaikan::class, 'prosesMassal']);
    });

    // --- Jabatan: Waka Sarpras ---
    Route::middleware(['jabatan:Waka Sarpras'])->prefix('sarpras')->group(function () {
        Route::post('media/mass-destroy', [SarprasMedia::class, 'massDestroy']);
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

        Route::post('pesan/mark-all-read', [PesanController::class, 'markAllAsRead']);
        Route::patch('pesan/{pesan}/status', [HumasPesan::class, 'updateStatus']);
        Route::apiResource('pesan', HumasPesan::class)->except(['store']);

        Route::get('ppdb-link', [HumasPpdb::class, 'index']);
        Route::put('ppdb-link', [HumasPpdb::class, 'update']);
    });

    // --- Jabatan: Kepala Sekolah ---
    Route::middleware(['jabatan:Kepala Sekolah'])->prefix('kepsek')->name('kepsek.')->group(function () {
        Route::post('profil-sekolah', [KepsekProfil::class, 'update']);
        Route::get('profil-sekolah', [KepsekProfil::class, 'index']);
        Route::get('log', [KepsekLog::class, 'index']);
        Route::get('setting/general', [KepsekSetting::class, 'index']);
        Route::post('setting/general', [KepsekSetting::class, 'updateGeneral']);
        Route::get('list-kelas', [KepsekPresensi::class, 'listKelas']);
        Route::get('list-siswa', [KepsekPresensi::class, 'listSiswaPresensi']);
        Route::get('jadwal-hari-ini', [KepsekPresensiMapel::class, 'listJadwalHariIni']);
        Route::get('siswa-by-jadwal/{id}', [KepsekPresensiMapel::class, 'getSiswaByJadwal']);
        Route::get('monitoring-poin-siswa', [KepsekPoin::class, 'index']); 
        Route::get('presensi-mapel/export', [KepsekPresensiMapel::class, 'export']);
        Route::get('presensi/export', [KepsekPresensi::class, 'export']); 
        Route::get('poin-siswa/export', [KepsekPoin::class, 'export']);
        Route::apiResource('poin_siswa', KepsekPoin::class);
        Route::apiResource('monitoring-presensi-harian', KepsekPresensi::class);
        Route::apiResource('monitoring-presensi-mapel', KepsekPresensiMapel::class);
    });

    // --- Jabatan: Ketua Jurusan ---
    Route::middleware(['jabatan:Ketua Jurusan'])->prefix('jurusan')->group(function () {
        Route::get('siswa', [JurusanSiswa::class, 'index']);
        Route::get('siswa/export', [JurusanSiswa::class, 'export']);
        Route::get('mapel', [JurusanMapel::class, 'index']);
        Route::get('mapel/export', [JurusanMapel::class, 'export']);
        Route::get('mapel/{id}', [JurusanMapel::class, 'show']);
        Route::get('guru-mapel/export', [JurusanGuruMapel::class, 'export']);
        Route::get('guru_mapel', [JurusanGuruMapel::class, 'index']);
        Route::get('kelas/export', [JurusanKelas::class, 'export']);
        Route::apiResource('kelas', JurusanKelas::class);
    });

    // --- Jabatan: Wali Kelas ---
    Route::prefix('walikelas')->group(function () {
        Route::get('siswa/export', [WaliSiswa::class, 'export']); 
        Route::get('orangtua/export', [WaliOrtu::class, 'export']); 
        Route::get('presensi/export', [WaliPresensi::class, 'export']);
        Route::get('list-siswa/{kelas_id}', [WaliPresensi::class, 'listSiswaPresensi']);
        Route::apiResource('siswa', WaliSiswa::class);
        Route::apiResource('orangtua', WaliOrtu::class);
        Route::apiResource('presensi', WaliPresensi::class);
    });

    // --- Guru Mapel General ---
    Route::prefix('mapel')->name('mapel.')->group(function () {
        Route::get('jadwal-hari-ini', [GuruPresensiMapel::class, 'listJadwalHariIni']);
        Route::get('siswa-by-jadwal/{id}', [GuruPresensiMapel::class, 'getSiswaByJadwal']);
        Route::get('presensi/export', [GuruPresensiMapel::class, 'export']);
        Route::apiResource('presensi', GuruPresensiMapel::class);
    });
});

/*
|--------------------------------------------------------------------------
| 6. SISWA & ORANG TUA ROUTES
|--------------------------------------------------------------------------
*/

// --- Role: Siswa ---
Route::prefix('siswa')->name('siswa.')->middleware(['auth.token', 'role:siswa'])->group(function () {
    Route::get('dashboard', [SiswaDashboard::class, 'index']);
    Route::get('presensi-saya', [SiswaPresensi::class, 'index']);
    Route::get('poin-saya', [SiswaPoin::class, 'index']); 
    Route::get('jam-sekolah/export', [SiswaJamSekolah::class, 'export']);
    Route::get('jadwal-mapel/export-pdf', [SiswaMapel::class, 'exportPdf']);
    Route::apiResource('jam_sekolah', SiswaJamSekolah::class);
    Route::apiResource('jadwal-mapel', SiswaMapel::class);

     
});

// --- Role: Orang Tua ---
Route::prefix('ortu')->name('ortu.')->middleware(['auth.token', 'role:orangtua'])->group(function () {
    Route::get('dashboard', [OrtuDashboard::class, 'index']);
    Route::get('list-anak', [OrtuPresensi::class, 'listAnak']);
    Route::get('presensi-anak', [OrtuPresensi::class, 'index']);
    Route::get('poin-anak', [OrtuPoin::class, 'index']); 
    Route::get('jam-sekolah/export', [OrtuJamSekolah::class, 'export']);
    Route::apiResource('jam_sekolah', OrtuJamSekolah::class);
     
});