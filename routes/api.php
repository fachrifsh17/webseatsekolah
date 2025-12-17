<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\{
    AlbumApiController,
    BannerApiController,
    BeritaApiController,
    FasilitasApiController,
    GuruApiController,
    JurusanApiController,
    MapelApiController,
    PengumumanApiController,
    PrestasiApiController,
    DataKontakApiController,
    EkstrakurikulerApiController,
    KalenderApiController,
    KurikulumApiController,
    LogAdminApiController,
    MediaApiController,
    PortalApiController,
    PPDBLinkApiController,
    ProfilApiController,
    SettingApiController,
    StrukturJabatanApiController,
    RoleApiController,
    UserApiController
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// =====================
// AUTH (PUBLIC)
// =====================
Route::post('auth/register', [AuthApiController::class, 'register']);
Route::post('auth/login', [AuthApiController::class, 'login']);

// =====================
// PUBLIC (READ ONLY)
// =====================
// Data Koleksi (Menggunakan apiResource)
Route::apiResource('berita', BeritaApiController::class)->only(['index','show']);
Route::apiResource('pengumuman', PengumumanApiController::class)->only(['index','show']);
Route::apiResource('prestasi', PrestasiApiController::class)->only(['index','show']);
Route::apiResource('fasilitas', FasilitasApiController::class)->only(['index','show']);
Route::apiResource('jurusan', JurusanApiController::class)->only(['index','show']);
Route::apiResource('mapel', MapelApiController::class)->only(['index','show']);
Route::apiResource('album', AlbumApiController::class)->only(['index','show']);
Route::apiResource('media', MediaApiController::class)->only(['index','show']);
Route::apiResource('ekstrakurikuler', EkstrakurikulerApiController::class)->only(['index','show']);
Route::apiResource('kalender', KalenderApiController::class)->only(['index','show']);
Route::apiResource('kurikulum', KurikulumApiController::class)->only(['index','show']);
Route::apiResource('portal', PortalApiController::class)->only(['index','show']);
Route::apiResource('guru', GuruApiController::class)->only(['index','show']);
Route::get('banner', [BannerApiController::class, 'index']);


// Data Setting Tunggal (Menggunakan GET ke method 'show' tanpa ID)
Route::get('profil', [ProfilApiController::class, 'show']);
Route::get('ppdb', [PPDBLinkApiController::class, 'show']);
Route::get('setting', [SettingApiController::class, 'show']);
Route::get('datakontak', [DataKontakApiController::class, 'show']); // <-- BARU: Rute Public Read Data Kontak


// =====================
// PROTECTED (LOGIN)
// =====================
Route::middleware('auth:sanctum')->group(function () {

    Route::post('auth/logout', [AuthApiController::class, 'logout']);
    Route::get('auth/me', [AuthApiController::class, 'me']);

    // --- ADMIN CRUD API RESOURCES (Koleksi Data) ---
    
    // Rute CRUD Penuh
    Route::apiResource('admin/berita', BeritaApiController::class);
    Route::apiResource('admin/pengumuman', PengumumanApiController::class);
    Route::apiResource('admin/prestasi', PrestasiApiController::class);
    Route::apiResource('admin/fasilitas', FasilitasApiController::class);
    Route::apiResource('admin/jurusan', JurusanApiController::class);
    Route::apiResource('admin/mapel', MapelApiController::class);
    Route::apiResource('admin/album', AlbumApiController::class);
    Route::apiResource('admin/media', MediaApiController::class);
    Route::apiResource('admin/ekstrakurikuler', EkstrakurikulerApiController::class);
    Route::apiResource('admin/kalender', KalenderApiController::class);
    Route::apiResource('admin/kurikulum', KurikulumApiController::class);
    Route::apiResource('admin/portal', PortalApiController::class);
    Route::apiResource('admin/banner', BannerApiController::class);
    Route::apiResource('admin/guru', GuruApiController::class);
    Route::apiResource('admin/role', RoleApiController::class);
    Route::apiResource('admin/user', UserApiController::class);
    
    // Rute CRUD Tanpa 'destroy'
    Route::apiResource('admin/log', LogAdminApiController::class)->except(['destroy']);

    // Rute Data Tunggal (UPDATE SAJA) - Menggunakan POST untuk Update single record
    Route::post('admin/profil', [ProfilApiController::class, 'update']);
    Route::post('admin/ppdb', [PPDBLinkApiController::class, 'update']);
    Route::post('admin/setting', [SettingApiController::class, 'update']);
    Route::post('admin/struktur', [StrukturJabatanApiController::class, 'update']);
    Route::post('admin/datakontak', [DataKontakApiController::class, 'update']); // <-- BARU: Rute Admin Update Data Kontak
});