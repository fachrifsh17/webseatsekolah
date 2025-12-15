<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\{
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
    SettingController
};

/*
|--------------------------------------------------------------------------
| WEB ROUTES (ADMIN PANEL)
|--------------------------------------------------------------------------
| Semua route backend admin, rapi, konsisten, dan siap dikunci middleware
|--------------------------------------------------------------------------
*/

// Batasi semua parameter ID hanya angka
Route::pattern('id', '[0-9]+');

// Halaman root (sementara)
Route::get('/', function () {
    return view('welcome');
});

// =====================
// ADMIN PANEL ROUTES
// =====================
Route::prefix('admin')
    ->as('admin.')
    // ->middleware(['auth', 'verified']) // aktifkan jika auth siap
    ->group(function () {

        // Dashboard (opsional tapi disarankan)
        Route::get('/', function () {
            return redirect()->route('admin.berita.index');
        })->name('dashboard');

        // CRUD Resources
        Route::resources([
            'berita'      => BeritaController::class,
            'pengumuman'  => PengumumanController::class,
            'prestasi'    => PrestasiController::class,
            'fasilitas'   => FasilitasController::class,
            'jurusan'     => JurusanController::class,
            'mapel'       => MapelController::class,
            'album'       => AlbumController::class,
            'banner'      => BannerController::class,
            'guru'        => GuruController::class,
        ]);

        // Kurikulum (method terbatas)
        Route::resource('kurikulum', KurikulumController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Setting
        Route::get('setting', [SettingController::class, 'index'])->name('setting.index');
        Route::post('setting', [SettingController::class, 'update'])->name('setting.update');
});
