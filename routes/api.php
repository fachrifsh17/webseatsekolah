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
    PrestasiApiController
};

/*
|--------------------------------------------------------------------------
| API Routes (tanpa prefix v1)
|--------------------------------------------------------------------------
| Semua endpoint otomatis berada di bawah /api/
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
Route::apiResource('berita', BeritaApiController::class)->only(['index','show']);
Route::apiResource('pengumuman', PengumumanApiController::class)->only(['index','show']);
Route::apiResource('prestasi', PrestasiApiController::class)->only(['index','show']);
Route::apiResource('fasilitas', FasilitasApiController::class)->only(['index','show']);
Route::apiResource('jurusan', JurusanApiController::class)->only(['index','show']);
Route::apiResource('mapel', MapelApiController::class)->only(['index','show']);
Route::apiResource('album', AlbumApiController::class)->only(['index','show']);
Route::get('banner', [BannerApiController::class, 'index']);

// =====================
// PROTECTED (LOGIN)
// =====================
Route::middleware('auth:sanctum')->group(function () {

    Route::post('auth/logout', [AuthApiController::class, 'logout']);
    Route::get('auth/me', [AuthApiController::class, 'me']);

    // ADMIN CRUD
    Route::apiResource('admin/berita', BeritaApiController::class);
    Route::apiResource('admin/pengumuman', PengumumanApiController::class);
    Route::apiResource('admin/prestasi', PrestasiApiController::class);
    Route::apiResource('admin/fasilitas', FasilitasApiController::class);
    Route::apiResource('admin/jurusan', JurusanApiController::class);
    Route::apiResource('admin/mapel', MapelApiController::class);
    Route::apiResource('admin/album', AlbumApiController::class);
    Route::apiResource('admin/banner', BannerApiController::class);
    Route::apiResource('admin/guru', GuruApiController::class);
});
