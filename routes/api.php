<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\{
    AlbumApiController, BannerApiController, BeritaApiController,
    FasilitasApiController, GuruApiController, JurusanApiController,
    MapelApiController, PengumumanApiController, PrestasiApiController,
    DataKontakApiController, EkstrakurikulerApiController, KalenderApiController,
    KurikulumApiController, LogAdminApiController, MediaApiController,
    PortalApiController, PPDBLinkApiController, ProfilApiController,
    SettingApiController, StrukturJabatanApiController, RoleApiController,
    UserApiController
};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================================
// 1. AUTH & PUBLIC (Tanpa Token)
// ==========================================
Route::prefix('public')->group(function () {
    
    // Auth
    Route::post('login', [AuthApiController::class, 'login']);

    // Data Read-Only (Hanya index & show untuk pengunjung)
    Route::apiResource('berita', BeritaApiController::class)->only(['index','show'])->parameters(['berita' => 'berita']);
    Route::apiResource('fasilitas', FasilitasApiController::class)->only(['index','show'])->parameters(['fasilitas' => 'fasilitas']);
    Route::apiResource('media', MediaApiController::class)->only(['index','show'])->parameters(['media' => 'media']);
    
    Route::apiResource('pengumuman', PengumumanApiController::class)->only(['index','show']);
    Route::apiResource('prestasi', PrestasiApiController::class)->only(['index','show']);
    Route::apiResource('jurusan', JurusanApiController::class)->only(['index','show']);
    Route::apiResource('mapel', MapelApiController::class)->only(['index','show']);
    Route::apiResource('album', AlbumApiController::class)->only(['index','show']);
    Route::apiResource('ekstrakurikuler', EkstrakurikulerApiController::class)->only(['index','show']);
    Route::apiResource('guru', GuruApiController::class)->only(['index','show']);
    
    Route::get('banner', [BannerApiController::class, 'index']);
    Route::get('profil', [ProfilApiController::class, 'show']);
    Route::get('setting', [SettingApiController::class, 'show']);
    Route::get('datakontak', [DataKontakApiController::class, 'show']);
});

// ==========================================
// 2. PROTECTED ADMIN (Wajib api_token)
// ==========================================
// Menggunakan auth:api karena kita pakai kolom api_token manual
Route::middleware('auth:api')->prefix('admin')->group(function () {

    Route::post('register', [AuthApiController::class, 'register']); // Register ditaruh di sini agar aman
    Route::post('logout', [AuthApiController::class, 'logout']);
    Route::get('me', [AuthApiController::class, 'me']);

    // --- ADMIN CRUD (Full Access) ---
    Route::apiResource('berita', BeritaApiController::class)->parameters(['berita' => 'berita']);
    Route::apiResource('fasilitas', FasilitasApiController::class)->parameters(['fasilitas' => 'fasilitas']);
    Route::apiResource('media', MediaApiController::class)->parameters(['media' => 'media']);

    Route::apiResource('pengumuman', PengumumanApiController::class);
    Route::apiResource('prestasi', PrestasiApiController::class);
    Route::apiResource('jurusan', JurusanApiController::class);
    Route::apiResource('mapel', MapelApiController::class);
    Route::apiResource('album', AlbumApiController::class);
    Route::apiResource('ekstrakurikuler', EkstrakurikulerApiController::class);
    Route::apiResource('kalender', KalenderApiController::class);
    Route::apiResource('kurikulum', KurikulumApiController::class);
    Route::apiResource('portal', PortalApiController::class);
    Route::apiResource('banner', BannerApiController::class);
    Route::apiResource('guru', GuruApiController::class);
    Route::apiResource('role', RoleApiController::class);
    Route::apiResource('user', UserApiController::class);
    Route::apiResource('log', LogAdminApiController::class)->only(['index', 'show']);

    // --- Rute Update Data Tunggal ---
    Route::post('profil', [ProfilApiController::class, 'update']);
    Route::post('ppdb', [PPDBLinkApiController::class, 'update']);
    Route::post('setting', [SettingApiController::class, 'update']);
    Route::post('struktur', [StrukturJabatanApiController::class, 'update']);
    Route::post('datakontak', [DataKontakApiController::class, 'update']);
});