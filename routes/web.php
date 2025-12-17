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
    SettingController,
    DataKontakController,
    EkstrakurikulerController,
    KalenderController,
    MediaController,
    PortalController,
    PpdbLinkController,
    ProfilSekolahController,
    RoleController,
    StrukturJabatanController,
    UserController,
    LogAdminController
};

/*
|--------------------------------------------------------------------------
| WEB ROUTES (ADMIN PANEL)
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
    // ->middleware(['auth', 'verified']) 
    ->group(function () {

        // Dashboard (opsional tapi disarankan)
        Route::get('/', function () {
            return redirect()->route('admin.berita.index');
        })->name('dashboard');

        // --- 1. CRUD Resources Penuh (Koleksi Data) ---
        // Resource ini memiliki index, create, store, show, edit, update, destroy
        Route::resources([
            'berita'          => BeritaController::class,
            'pengumuman'      => PengumumanController::class,
            'prestasi'        => PrestasiController::class,
            'fasilitas'       => FasilitasController::class,
            'jurusan'         => JurusanController::class,
            'mapel'           => MapelController::class,
            'album'           => AlbumController::class,
            'banner'          => BannerController::class,
            'guru'            => GuruController::class,
            'ekstrakurikuler' => EkstrakurikulerController::class,
            'kalender'        => KalenderController::class,
            'media'           => MediaController::class,
            'portal'          => PortalController::class,
            'role'            => RoleController::class,
            'user'            => UserController::class,
        ]);
        
        // --- 2. Resources dengan Pembatasan Method ---
        
        // Log Admin (Tidak perlu Create, Edit, Update, Destroy)
        Route::resource('log', LogAdminController::class)
            ->only(['index', 'show']); 

        // Kurikulum (Sesuai kebutuhan Anda: hanya index, store, update, destroy)
        Route::resource('kurikulum', KurikulumController::class)
            ->only(['index', 'store', 'update', 'destroy']);
            
        // --- 3. Single-Row Resources (Data Tunggal: Hanya Edit/Index & Update) ---
        // Karena data ini hanya ada 1 record, kita gunakan method 'only' atau GET/POST langsung.

        // Profil Sekolah
        Route::resource('profil', ProfilSekolahController::class)
            ->only(['index', 'edit', 'update']); 
            
        // Setting
        Route::resource('setting', SettingController::class)
            ->only(['index', 'edit', 'update']); 
            
        // Data Kontak
        Route::resource('datakontak', DataKontakController::class)
            ->only(['index', 'edit', 'update']);

        // PPDB Link
        Route::resource('ppdb', PpdbLinkController::class)
            ->only(['index', 'edit', 'update']);
            
        // Struktur Jabatan
        Route::resource('struktur', StrukturJabatanController::class)
            ->only(['index', 'edit', 'update']);
            
        // Catatan: Jika Anda hanya menggunakan satu fungsi Index untuk menampilkan form edit
        // Anda bisa mengganti: ->only(['index', 'update'])
        // Namun, ->only(['index', 'edit', 'update']) lebih standar untuk Resources.
});